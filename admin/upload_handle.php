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

// Get POST params
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

// Handle file chunk or full upload
if ($totalChunks > 0) {
    // Chunked upload
if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
    if ($id) {
        // No video means just update metadata
        $query = "UPDATE movies SET title = ?, genre = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssi', $title, $genre, $id);
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

    // Save chunk file: temp/{fileName}_chunk{chunkNumber}
    $chunkFile = $tempDir . $fileName . '_chunk' . $chunkNumber;
    if (!move_uploaded_file($chunk['tmp_name'], $chunkFile)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save chunk']);
        exit;
    }

    // If last chunk, merge chunks
    if ($chunkNumber === $totalChunks) {
        $finalFileName = uniqid() . '_' . basename($fileName);
        $finalFilePath = $uploadDir . $finalFileName;

        $outHandle = fopen($finalFilePath, 'wb');
        if (!$outHandle) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create final file']);
            exit;
        }

        for ($i = 1; $i <= $totalChunks; $i++) {
            $chunkPath = $tempDir . $fileName . '_chunk' . $i;
            $inHandle = fopen($chunkPath, 'rb');
            if (!$inHandle) {
                fclose($outHandle);
                http_response_code(500);
                echo json_encode(['error' => "Failed to open chunk $i"]);
                exit;
            }
            while (!feof($inHandle)) {
                $buffer = fread($inHandle, 1048576); // 1MB buffer
                fwrite($outHandle, $buffer);
            }
            fclose($inHandle);
            unlink($chunkPath); // delete chunk after merging
        }
        fclose($outHandle);

        // If editing, delete old file
        if ($id) {
            $query = "SELECT video FROM movies WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $oldVideo = $row['video'];
                if ($oldVideo && file_exists($oldVideo)) unlink($oldVideo);
            }
            mysqli_stmt_close($stmt);

            // Update DB with new data and video path
            $query = "UPDATE movies SET title = ?, genre = ?, video = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $finalFilePath, $id);
        } else {
            // Insert new movie
            $query = "INSERT INTO movies (title, genre, video) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sss', $title, $genre, $finalFilePath);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);

        echo json_encode(['success' => true, 'message' => 'Upload and merge complete']);
        exit;
    } else {
        // Just uploaded a chunk
        echo json_encode(['success' => true, 'message' => "Chunk $chunkNumber uploaded"]);
        exit;
    }
} else {
    // Normal full upload for small files (<200MB)
   if (!isset($_FILES['video']) || $_FILES['video']['error'] === UPLOAD_ERR_NO_FILE) {
    if ($id) {
        // No new video uploaded, keep existing one and update title/genre only
        $query = "UPDATE movies SET title = ?, genre = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssi', $title, $genre, $id);
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

    // Delete old file if editing
    if ($id) {
        $query = "SELECT video FROM movies WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $oldVideo = $row['video'];
            if ($oldVideo && file_exists($oldVideo)) unlink($oldVideo);
        }
        mysqli_stmt_close($stmt);

        // Update DB
        $query = "UPDATE movies SET title = ?, genre = ?, video = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $filePath, $id);
    } else {
        // Insert new
        $query = "INSERT INTO movies (title, genre, video) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'sss', $title, $genre, $filePath);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    echo json_encode(['success' => true, 'message' => 'Upload complete']);
    exit;
}
?>
