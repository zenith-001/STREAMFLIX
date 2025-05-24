<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

// Include database connection
include '../db.php';

// Handle the uploaded file
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $genre = $_POST['genre'];
    $videoFile = $_FILES['video'];

    // Check for errors
    if ($videoFile['error'] !== UPLOAD_ERR_OK) {
        die("Upload failed with error code " . $videoFile['error']);
    }

    // Check file size
    $maxFileSize = 200 * 1024 * 1024; // 200 MB
    if ($videoFile['size'] > $maxFileSize) {
        // Handle chunked upload
        $chunkSize = 5 * 1024 * 1024; // 5 MB chunks
        $filePath = 'uploads/' . uniqid() . '_' . basename($videoFile['name']);
        $fileHandle = fopen($filePath, 'wb');

        // Read the file in chunks
        $bytesRead = 0;
        while ($bytesRead < $videoFile['size']) {
            $chunk = fread($videoFile['tmp_name'], $chunkSize);
            fwrite($fileHandle, $chunk);
            $bytesRead += strlen($chunk);
        }
        fclose($fileHandle);
    } else {
        // Move the uploaded file to the uploads directory
        $filePath = '../uploads/' . uniqid() . '_' . basename($videoFile['name']);
        move_uploaded_file($videoFile['tmp_name'], $filePath);
    }

    // Insert movie details into the database
    $query = "INSERT INTO movies (title, genre, video) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'sss', $title, $genre, $filePath);
    mysqli_stmt_execute($stmt);

    // Check for successful insertion
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo "Movie uploaded successfully!";
    } else {
        echo "Error uploading movie.";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}
 header('Location: monitor.php');
?>
