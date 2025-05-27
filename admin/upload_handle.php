<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

include '../db.php';

$uploadDir = '../uploads/';
$tempDir = $uploadDir . 'temp/';

// Create temp dir if not exists
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0777, true);
}

$id = $_POST['id'] ?? null;
$title = $_POST['title'] ?? null;
$genre = $_POST['genre'] ?? null;
$totalChunks = isset($_POST['totalChunks']) ? intval($_POST['totalChunks']) : 0;
$chunkNumber = isset($_POST['chunkNumber']) ? intval($_POST['chunkNumber']) : 0;
$fileName = $_POST['fileName'] ?? null;

// Basic validation
if (!$title || !$genre) {
    log_upload_event("ERROR: Title or genre missing for upload");
    http_response_code(400);
    echo json_encode(['error' => 'Title and genre required']);
    exit;
}

// Get current DB values if editing
$oldVideo = $oldSubtitle = null;
if ($id) {
    $stmt = mysqli_prepare($conn, "SELECT video, subtitle FROM movies WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $oldVideo = $row['video'];
        $oldSubtitle = $row['subtitle'];
    }
    mysqli_stmt_close($stmt);
}

// Handle subtitle upload (set $subtitlePath as before, but delete old if new uploaded)
$subtitlePath = $oldSubtitle;
if (isset($_FILES['subtitle']) && $_FILES['subtitle']['error'] === UPLOAD_ERR_OK) {
    // Delete old subtitle if exists
    if ($oldSubtitle && file_exists($uploadDir . $oldSubtitle)) {
        unlink($uploadDir . $oldSubtitle);
    }
    // ...existing subtitle upload/conversion logic, set $subtitlePath...
    $subtitleFile = $_FILES['subtitle'];
    $originalName = basename($subtitleFile['name']);
    $isSrt = preg_match('/\.srt$/i', $originalName);
    $isVtt = preg_match('/\.vtt$/i', $originalName);
    $subtitleBase = preg_replace('/\.(srt|vtt)$/i', '', $originalName);
    $hlsDir = null;
    if (!empty($fileName)) {
        $videoBase = pathinfo($fileName, PATHINFO_FILENAME);
        $hlsDir = $uploadDir . $videoBase . '_hls' . DIRECTORY_SEPARATOR;
        if (!is_dir($hlsDir)) mkdir($hlsDir, 0777, true);
    } elseif ($id && $oldVideo) {
        $videoBase = pathinfo(basename($oldVideo), PATHINFO_FILENAME);
        $hlsDir = $uploadDir . $videoBase . '_hls' . DIRECTORY_SEPARATOR;
        if (!is_dir($hlsDir)) mkdir($hlsDir, 0777, true);
    }
    if ($hlsDir) {
        $targetVtt = $hlsDir . $subtitleBase . '.vtt';
        if ($isSrt) {
            $tmpSrt = $hlsDir . $subtitleBase . '.srt';
            if (!move_uploaded_file($subtitleFile['tmp_name'], $tmpSrt)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to move subtitle file']);
                exit;
            }
            $cmd = 'ffmpeg -y -i ' . escapeshellarg($tmpSrt) . ' ' . escapeshellarg($targetVtt) . ' 2>&1';
            $out = shell_exec($cmd);
            unlink($tmpSrt);
            if (!file_exists($targetVtt)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to convert SRT to VTT', 'ffmpeg' => $out]);
                exit;
            }
        } else {
            if (!move_uploaded_file($subtitleFile['tmp_name'], $targetVtt)) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to move subtitle file']);
                exit;
            }
        }
        $subtitlePath = basename($hlsDir) . '/' . $subtitleBase . '.vtt';
    }
}

// Handle video upload (set $filePath as before, but delete old if new uploaded)
$filePath = $oldVideo;
if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    // Delete old video and HLS folder if exists
    if ($oldVideo) {
        $hlsFolder = $uploadDir . pathinfo(basename($oldVideo), PATHINFO_FILENAME) . '_hls';
        if (is_dir($hlsFolder)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($hlsFolder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($hlsFolder);
        }
    }
    // ...existing video upload/HLS conversion logic, set $filePath...
    $file = $_FILES['video'];
    $tmpPath = $uploadDir . uniqid() . '_' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file']);
        exit;
    }
    $hlsDir = $uploadDir . pathinfo($tmpPath, PATHINFO_FILENAME) . '_hls/';
    if (!is_dir($hlsDir)) {
        mkdir($hlsDir, 0777, true);
    }
    $hlsPlaylist = $hlsDir . 'index.m3u8';
    $ffmpegCmd = "ffmpeg -i " . escapeshellarg($tmpPath) . " -profile:v baseline -level 3.0 -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($hlsPlaylist) . " 2>&1";
    $output = shell_exec($ffmpegCmd);
    if (!file_exists($hlsPlaylist)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to convert video to HLS', 'ffmpeg_output' => $output]);
        exit;
    }
    unlink($tmpPath);
    $filePath = 'uploads/' . basename($hlsDir) . '/index.m3u8';
}

// If editing and no new files, just update metadata
if ($id) {
    $query = "UPDATE movies SET title = ?, genre = ?, video = ?, subtitle = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $filePath, $subtitlePath, $id);
    mysqli_stmt_execute($stmt);
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Movie updated']);
    exit;
}

// Full upload (non-chunked)
if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
    if ($id) {
        $query = "UPDATE movies SET title = ?, genre = ?" . ($subtitlePath ? ", subtitle = ?" : "") . " WHERE id = ?";
        if ($subtitlePath) {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $subtitlePath, $id);
        } else {
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssi', $title, $genre, $id);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        log_upload_event("SUCCESS: Movie updated without changing video for '$title' (ID: $id)");
        echo json_encode(['success' => true, 'message' => 'Movie updated without changing video']);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Video file is required for new upload']);
        exit;
    }
}

$file = $_FILES['video'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    log_upload_event("ERROR: Upload error for '$title' (ID: $id): " . $file['error']);
    http_response_code(500);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

$filePath = $uploadDir . uniqid() . '_' . basename($file['name']);
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    log_upload_event("ERROR: Failed to move uploaded video file for '$title' (ID: $id)");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
    exit;
}

// Convert uploaded video to HLS (m3u8)
$hlsDir = $uploadDir . pathinfo($filePath, PATHINFO_FILENAME) . '_hls/';
if (!is_dir($hlsDir)) {
    mkdir($hlsDir, 0777, true);
}
$hlsPlaylist = $hlsDir . 'index.m3u8';
$ffmpegCmd = "ffmpeg -i " . escapeshellarg($filePath) . " -profile:v baseline -level 3.0 -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($hlsPlaylist) . " 2>&1";
$output = shell_exec($ffmpegCmd);
if (!file_exists($hlsPlaylist)) {
    log_upload_event("ERROR: Failed to convert video to HLS for '$title' (ID: $id): $output");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to convert video to HLS', 'ffmpeg_output' => $output]);
    exit;
}
// Optionally delete original mp4 to save space
unlink($filePath);
$filePath = $hlsPlaylist; // Store m3u8 path in DB

if ($id) {
    $query = "SELECT video FROM movies WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $oldVideo = $row['video'];
        if ($oldVideo && file_exists($oldVideo))
            unlink($oldVideo);
    }
    mysqli_stmt_close($stmt);

    $query = "UPDATE movies SET title = ?, genre = ?, video = ?" . ($subtitlePath ? ", subtitle = ?" : "") . " WHERE id = ?";
    if ($subtitlePath) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $filePath, $subtitlePath, $id);
    } else {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $filePath, $id);
    }
} else {
    $query = "INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssss', $title, $genre, $filePath, $subtitlePath);
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

log_upload_event("SUCCESS: Uploaded movie '$title' (ID: $id)");
echo json_encode(['success' => true, 'message' => 'Upload complete']);
exit;

// Helper: log to uploads/log.txt
function log_upload_event($message) {
    $logFile = __DIR__ . '/../uploads/log.txt';
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
}
?>
