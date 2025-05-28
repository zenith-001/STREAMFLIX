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
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move subtitle file']);
        exit;
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

            echo json_encode(['success' => true, 'message' => 'Movie updated without changing video']);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            exit;
        }
    }

    $chunk = $_FILES['video'];
    if ($chunk['error'] !== UPLOAD_ERR_OK) {
        http_response_code(500);
        echo json_encode(['error' => 'Chunk upload error: ' . $chunk['error']]);
        exit;
    }

    $chunkFile = $tempDir . $fileName . '_chunk' . $chunkNumber;
    if (!move_uploaded_file($chunk['tmp_name'], $chunkFile)) {
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

        // Convert to HLS format
        $hlsDir = $uploadDir . 'hls/';
        if (!is_dir($hlsDir)) {
            mkdir($hlsDir, 0777, true);
        }
        $hlsFileName = pathinfo($finalFileName, PATHINFO_FILENAME);
        $hlsPlaylistPath = $hlsDir . $hlsFileName . '.m3u8';

        $ffmpegCommand = "ffmpeg -i " . escapeshellarg($finalFilePath) . " -codec: copy -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($hlsPlaylistPath);
        exec($ffmpegCommand, $output, $return_var);
        if ($return_var !== 0) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to convert video to HLS format']);
            exit;
        }

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
            mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $hlsPlaylistPath, $subtitlePath, $id);
        } else {
            $query = "INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssss', $title, $genre, $hlsPlaylistPath, $subtitlePath);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        echo json_encode(['success' => true, 'message' => 'Upload and merge complete']);
        exit;
    } else {
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
    http_response_code(500);
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

$filePath = $uploadDir . uniqid() . '_' . basename($file['name']);
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file']);
    exit;
}

// Convert to HLS format
$hlsDir = $uploadDir . 'hls/';
if (!is_dir($hlsDir)) {
    mkdir($hlsDir, 0777, true);
}
$hlsFileName = pathinfo($filePath, PATHINFO_FILENAME);
$hlsPlaylistPath = $hlsDir . $hlsFileName . '.m3u8';

$ffmpegCommand = "ffmpeg -i " . escapeshellarg($filePath) . " -codec: copy -start_number 0 -hls_time 10 -hls_list_size 0 -f hls " . escapeshellarg($hlsPlaylistPath);
exec($ffmpegCommand, $output, $return_var);
if ($return_var !== 0) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to convert video to HLS format']);
    exit;
}

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
        mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $hlsPlaylistPath, $subtitlePath, $id);
    } else {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $hlsPlaylistPath, $id);
    }
} else {
    $query = "INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssss', $title, $genre, $hlsPlaylistPath, $subtitlePath);
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode(['success' => true, 'message' => 'Upload complete']);
exit;
?>
