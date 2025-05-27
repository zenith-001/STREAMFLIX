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

// Handle subtitle upload
$subtitlePath = null;
if (isset($_FILES['subtitle']) && $_FILES['subtitle']['error'] === UPLOAD_ERR_OK) {
    $subtitleFile = $_FILES['subtitle'];
    $subtitlePath = $uploadDir . uniqid() . '_' . basename($subtitleFile['name']);
    if (!move_uploaded_file($subtitleFile['tmp_name'], $subtitlePath)) {
        log_upload_event("ERROR: Failed to move subtitle file for '$title' (ID: $id)");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move subtitle file']);
        exit;
    } else {
        log_upload_event("SUCCESS: Subtitle uploaded for '$title' (ID: $id)");
    }
}

// 🔧 Handle subtitle-only update (NO video, NO chunks)
if (!$totalChunks && !isset($_FILES['video']) && $id && $subtitlePath) {
    $query = "UPDATE movies SET title = ?, genre = ?, subtitle = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $subtitlePath, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    log_upload_event("SUCCESS: Subtitle-only update for '$title' (ID: $id)");
    echo json_encode(['success' => true, 'message' => 'Movie updated with new subtitle only']);
    exit;
}

// Handle chunked upload
if ($totalChunks > 0) {
    if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
        if ($id) {
            // Update only metadata (no new video)
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
            log_upload_event("ERROR: No video file uploaded for chunked upload of '$title' (ID: $id)");
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            exit;
        }
    }

    $chunk = $_FILES['video'];
    if ($chunk['error'] !== UPLOAD_ERR_OK) {
        log_upload_event("ERROR: Chunk upload error for '$title' (ID: $id): " . $chunk['error']);
        http_response_code(500);
        echo json_encode(['error' => 'Chunk upload error: ' . $chunk['error']]);
        exit;
    }

    $chunkFile = $tempDir . $fileName . '_chunk' . $chunkNumber;
    if (!move_uploaded_file($chunk['tmp_name'], $chunkFile)) {
        log_upload_event("ERROR: Failed to save chunk for '$title' (ID: $id), chunk $chunkNumber");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save chunk']);
        exit;
    }

    if ($chunkNumber === $totalChunks) {
        $finalFileName = uniqid() . '_' . basename($fileName);
        $finalFilePath = $uploadDir . $finalFileName;

        $outHandle = fopen($finalFilePath, 'wb');
        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunkPath = $tempDir . $fileName . '_chunk' . $i;
            $inHandle = fopen($chunkPath, 'rb');
            while (!feof($inHandle)) {
                fwrite($outHandle, fread($inHandle, 1048576));
            }
            fclose($inHandle);
            unlink($chunkPath);
        }
        fclose($outHandle);

        // Convert merged mp4 to HLS (m3u8)
        $hlsDir = $uploadDir . pathinfo($finalFilePath, PATHINFO_FILENAME) . '_hls/';
        if (!is_dir($hlsDir)) {
            mkdir($hlsDir, 0777, true);
        }
        $hlsPlaylist = $hlsDir . 'index.m3u8';
        $ffmpegCmd = "ffmpeg -i " . escapeshellarg($finalFilePath) . " -profile:v baseline -level 3.0 -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($hlsPlaylist) . " 2>&1";
        $output = shell_exec($ffmpegCmd);
        if (!file_exists($hlsPlaylist)) {
            log_upload_event("ERROR: Failed to convert merged video to HLS for '$title' (ID: $id): $output");
            http_response_code(500);
            echo json_encode(['error' => 'Failed to convert video to HLS', 'ffmpeg_output' => $output]);
            exit;
        }
        unlink($finalFilePath);
        $finalFilePath = $hlsPlaylist; // Store m3u8 path in DB

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

            $query = "UPDATE movies SET title = ?, genre = ?, video = ?, subtitle = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $finalFilePath, $subtitlePath, $id);
        } else {
            $query = "INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssss', $title, $genre, $finalFilePath, $subtitlePath);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        log_upload_event("SUCCESS: Uploaded and merged movie '$title' (ID: $id)");
        echo json_encode(['success' => true, 'message' => 'Upload and merge complete']);
        exit;
    } else {
        log_upload_event("SUCCESS: Uploaded chunk $chunkNumber of $totalChunks for '$title' (ID: $id)");
        echo json_encode(['success' => true, 'message' => "Chunk $chunkNumber uploaded"]);
        exit;
    }
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

function log_upload_event($message) {
    $logFile = __DIR__ . '/../uploads/log.txt';
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
}
?>
