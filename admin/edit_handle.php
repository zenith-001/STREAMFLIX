<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

include '../db.php';

$id = $_POST['id'] ?? '';
$title = $_POST['title'] ?? '';
$genre = $_POST['genre'] ?? '';
$subtitlePath = '';

if (!$id || !$title || !$genre) {
    http_response_code(400);
    echo json_encode(['message' => 'ID, title, and genre are required']);
    exit;
}

// Function to convert SRT content to VTT content
function srtToVtt($srtContent) {
    // Add WEBVTT header
    $vtt = "WEBVTT\n\n";

    // Replace commas in timestamps with dots for milliseconds
    $lines = explode("\n", $srtContent);
    foreach ($lines as $line) {
        // convert timestamps format: 00:00:20,000 --> 00:00:24,400 to 00:00:20.000 --> 00:00:24.400
        if (preg_match('/(\d{2}:\d{2}:\d{2}),(\d{3}) --> (\d{2}:\d{2}:\d{2}),(\d{3})/', $line)) {
            $line = preg_replace('/,/', '.', $line);
        }
        $vtt .= $line . "\n";
    }
    return $vtt;
}

// Handle subtitle upload if available
if (isset($_FILES['subtitle']) && $_FILES['subtitle']['error'] === UPLOAD_ERR_OK) {
    $subtitleFile = $_FILES['subtitle'];
    $ext = pathinfo($subtitleFile['name'], PATHINFO_EXTENSION);
    $uniqueName = uniqid() . '_' . basename($subtitleFile['name']);
    $uploadDir = '../uploads/subtitles/';
    $subtitlePath = $uploadDir . $uniqueName;

    if (!move_uploaded_file($subtitleFile['tmp_name'], $subtitlePath)) {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to move subtitle file']);
        exit;
    }

    // If uploaded file is .srt, convert it to .vtt
    if (strtolower($ext) === 'srt') {
        $srtContent = file_get_contents($subtitlePath);
        $vttContent = srtToVtt($srtContent);

        // Save as .vtt file instead of .srt
        $vttPath = preg_replace('/\.srt$/i', '.vtt', $subtitlePath);

        if (file_put_contents($vttPath, $vttContent) === false) {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to save VTT subtitle']);
            exit;
        }

        // Delete original .srt file
        unlink($subtitlePath);

        // Update path to new .vtt file
        $subtitlePath = $vttPath;
    }
}

// Update query
$query = "UPDATE movies SET title = ?, genre = ?" . ($subtitlePath ? ", subtitle = ?" : "") . " WHERE id = ?";
$stmt = $conn->prepare($query);

if ($subtitlePath) {
    $stmt->bind_param("sssi", $title, $genre, $subtitlePath, $id);
} else {
    $stmt->bind_param("ssi", $title, $genre, $id);
}

if ($stmt->execute()) {
    echo json_encode(['message' => 'Movie updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to update movie: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
