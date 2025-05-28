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
    $subtitlePath = '../uploads/' . uniqid() . '_' . basename($subtitleFile['name']);
    if (!move_uploaded_file($subtitleFile['tmp_name'], $subtitlePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move subtitle file']);
        exit;
    }
}


// Update movie details
$query = "UPDATE movies SET title = ?, genre = ?" . ($subtitlePath ? ", subtitle = ?" : "") . " WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
if ($subtitlePath) {
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $subtitlePath, $id);
} else {
    mysqli_stmt_bind_param($stmt, 'ssi', $title, $genre, $id);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);


echo json_encode(['success' => true, 'message' => 'Movie updated successfully']);
exit;
?>