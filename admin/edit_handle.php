<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

include '../db.php';

$id = $_POST['id'] ?? null;
$title = $_POST['title'] ?? null;
$genre = $_POST['genre'] ?? null;
$subtitlePath = null;

// Basic validation
if (!$id || !$title || !$genre) {
    http_response_code(400);
    echo json_encode(['error' => 'ID, title, and genre are required']);
    exit;
}

// Handle subtitle upload
if (isset($_FILES['subtitle']) && $_FILES['subtitle']['error'] === UPLOAD_ERR_OK) {
    $subtitleFile = $_FILES['subtitle'];
    $subtitlePath = '../uploads/subtitles/' . uniqid() . '_' . basename($subtitleFile['name']);
    if (!move_uploaded_file($subtitleFile['tmp_name'], $subtitlePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move subtitle file']);
        exit;
    }
}

// Handle video upload
$videoPath = null;
if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $videoFile = $_FILES['video'];
    $videoPath = '../uploads/videos/' . uniqid() . '_' . basename($videoFile['name']);
    if (!move_uploaded_file($videoFile['tmp_name'], $videoPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move video file']);
        exit;
    }
}

// Update movie details in the database
$query = "UPDATE movies SET title = ?, genre = ?" . ($subtitlePath ? ", subtitle = ?" : "") . ($videoPath ? ", video = ?" : "") . " WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);

if ($subtitlePath && $videoPath) {
    mysqli_stmt_bind_param($stmt, 'ssssi', $title, $genre, $subtitlePath, $videoPath, $id);
} elseif ($subtitlePath) {
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $subtitlePath, $id);
} elseif ($videoPath) {
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $videoPath, $id);
} else {
    mysqli_stmt_bind_param($stmt, 'ssi', $title, $genre, $id);
}

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    echo json_encode(['success' => true, 'message' => 'Movie updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update movie: ' . mysqli_error($conn)]);
}
?>
