<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    <div class="upload-container">
  <form id="uploadForm" enctype="multipart/form-data">
    <input type="hidden" name="id" id="movieId" value="<?php echo $id; ?>" />
    <input type="text" name="title" id="title" placeholder="Movie Title" value="<?php echo htmlspecialchars($title); ?>" required />
    <input type="text" name="genre" id="genre" placeholder="Movie Genre" value="<?php echo htmlspecialchars($genre); ?>" required />
    <input type="file" name="video" id="videoFile" />
    <input type="submit" value="<?php echo $id ? 'Update Movie' : 'Upload Movie'; ?>" />
  </form>

  <div id="messages" style="margin-top:1rem; font-weight: bold;"></div>
  <progress id="progressBar" value="0" max="100" style="width: 100%; display:none;"></progress>
</div>

<script>
const uploadForm = document.getElementById('uploadForm');
const progressBar = document.getElementById('progressBar');
const messages = document.getElementById('messages');
const MAX_CHUNK_SIZE = 200 * 1024 * 1024; // 200MB

uploadForm.addEventListener('submit', async function(e) {
  e.preventDefault();

  const title = document.getElementById('title').value.trim();
  const genre = document.getElementById('genre').value.trim();
  const videoInput = document.getElementById('videoFile');
  const file = videoInput.files[0];
  const id = document.getElementById('movieId').value;

  if (!title || !genre) {
    alert('Please enter title and genre');
    return;
  }
if (!file && !id) {
  alert('Please select a video file');
  return;
}

if (!file && id) {
  // Editing without uploading new video
  messages.innerHTML = 'Updating movie details...';

  const formData = new FormData();
  formData.append('id', id);
  formData.append('title', title);
  formData.append('genre', genre);

  const response = await fetch('upload_handle.php', {
    method: 'POST',
    body: formData,
  });

  const result = await response.json();
  if (result.success) {
    messages.innerHTML = result.message;
  } else {
    messages.innerHTML = 'Update failed: ' + (result.error || 'Unknown error');
  }

  return;
}


  messages.innerHTML = '1) Checking the file if its size is big or not...';
  progressBar.style.display = 'none';
  progressBar.value = 0;

  if (file.size <= MAX_CHUNK_SIZE) {
    // Small file - upload directly
    messages.innerHTML = '2) Uploading the file...';
    progressBar.style.display = 'block';

    const formData = new FormData();
    formData.append('id', id);
    formData.append('title', title);
    formData.append('genre', genre);
    formData.append('video', file);

    try {
      const response = await fetch('upload_handle.php', {
        method: 'POST',
        body: formData,
      });

      const result = await response.json();
      if (result.success) {
        progressBar.value = 100;
        messages.innerHTML = '3) Upload success!';
      } else {
        messages.innerHTML = 'Upload failed: ' + (result.error || 'Unknown error');
      }
    } catch (err) {
      messages.innerHTML = 'Upload error: ' + err.message;
    }
  } else {
    // Large file - chunked upload
    messages.innerHTML = '2) Breaking the file into chunks...';
    progressBar.style.display = 'block';

    const totalChunks = Math.ceil(file.size / MAX_CHUNK_SIZE);
    let uploadedChunks = 0;
    const fileName = file.name;

    async function uploadChunk(chunkNumber) {
      const start = (chunkNumber - 1) * MAX_CHUNK_SIZE;
      const end = Math.min(file.size, start + MAX_CHUNK_SIZE);
      const chunk = file.slice(start, end);

      messages.innerHTML = `3) Uploading chunk ${chunkNumber} of ${totalChunks}...`;

      const chunkFormData = new FormData();
      chunkFormData.append('id', id);
      chunkFormData.append('title', title);
      chunkFormData.append('genre', genre);
      chunkFormData.append('video', chunk);
      chunkFormData.append('chunkNumber', chunkNumber);
      chunkFormData.append('totalChunks', totalChunks);
      chunkFormData.append('fileName', fileName);

      const response = await fetch('upload_handle.php', {
        method: 'POST',
        body: chunkFormData,
      });

      const result = await response.json();
      if (result.success) {
        uploadedChunks++;
        progressBar.value = (uploadedChunks / totalChunks) * 100;

        if (chunkNumber < totalChunks) {
          await uploadChunk(chunkNumber + 1);
        } else {
          messages.innerHTML = `6) All chunks uploaded successfully.<br>7) Merging the chunks...`;
          // The server merges on last chunk upload, so just wait for success message
          messages.innerHTML = `8) Upload successful!`;
        }
      } else {
        throw new Error(result.error || 'Chunk upload failed');
      }
    }

    try {
      await uploadChunk(1);
    } catch (err) {
      messages.innerHTML = 'Upload error: ' + err.message;
    }
  }
});
</script>

  </div>
</body>
</html>
