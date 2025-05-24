<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

// Include database connection
include '../db.php';

// Initialize variables
$title = '';
$genre = '';
$video = '';
$id = null;

// Check if an ID is provided for editing
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT * FROM movies WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($movie = mysqli_fetch_assoc($result)) {
        $title = $movie['title'];
        $genre = $movie['genre'];
        $video = $movie['video'];
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin - Upload Movie</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: #121212;
      color: #e0e0e0;
      padding: 2rem;
    }
    .upload-container {
      background: #1f1f1f;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }
    input[type="text"], input[type="file"] {
      width: 100%;
      padding: 0.5rem;
      margin-bottom: 1rem;
      border: 1px solid #444;
      border-radius: 4px;
      background: #2a2a2a;
      color: #e0e0e0;
    }
    input[type="submit"] {
      background: #e50914;
      color: #fff;
      border: none;
      padding: 0.75rem;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.3s;
    }
    input[type="submit"]:hover {
      background: #d40813;
    }
  </style>
</head>
<body>
  <div class="upload-container">
    <h1><?php echo $id ? 'Edit Movie' : 'Upload Movie'; ?></h1>
    <form action="upload_handle.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?php echo $id; ?>" />
      <input type="text" name="title" placeholder="Movie Title" value="<?php echo htmlspecialchars($title); ?>" required />
      <input type="text" name="genre" placeholder="Movie Genre" value="<?php echo htmlspecialchars($genre); ?>" required />
      <input type="file" name="video" id="videoFile" />
      <input type="submit" value="<?php echo $id ? 'Update Movie' : 'Upload Movie'; ?>" />
    </form>
  </div>
</body>
</html>
