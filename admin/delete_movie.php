<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

// Include database connection
include '../db.php';

// Check if an ID is provided for deletion
if (isset($_POST['id'])) {
    $id = $_POST['id'];

    // Fetch the video path to delete the file
    $query = "SELECT video FROM movies WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $movie = mysqli_fetch_assoc($result);
    $videoPath = $movie['video'];

    // Delete the movie from the database
    $query = "DELETE FROM movies WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    // Check for successful deletion
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        // Delete the video file from the server
        if (file_exists($videoPath)) {
            unlink($videoPath); // Delete the file
        }
        echo "Movie deleted successfully!";
    } else {
        echo "Error deleting movie.";
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
} else {
    echo "No movie ID provided for deletion.";
}
header('Location: monitor.php');

?>