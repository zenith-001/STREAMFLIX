<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

include '../db.php';

// Initialize variables
$id = null;
$title = '';
$genre = '';
$videoPath = '';
$subtitlePath = '';

$editing = false;

// If editing, get existing movie data
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $editing = true;
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $movie = $result->fetch_assoc();
        $title = $movie['title'];
        $genre = $movie['genre'];
        $videoPath = $movie['video'];
        $subtitlePath = $movie['subtitle'];
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // On form submission, override variables with POST data
    $title = $_POST['title'] ?? '';
    $genre = $_POST['genre'] ?? '';

    if ($editing && isset($_POST['id'])) {
        $id = intval($_POST['id']);
    }

    // Redirect to the appropriate handler based on whether it's an edit or upload
    if ($editing) {
        header("Location: edit_handle.php");
        exit;
    } else {
        header("Location: upload_handle.php");
        exit;
    }
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

    input[type="text"],
    input[type="file"] {
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
    <form id="uploadForm" enctype="multipart/form-data">
      <input type="hidden" name="id" id="movieId" value="<?php echo $id; ?>" />
      <input type="text" name="title" id="title" placeholder="Movie Title" value="<?php echo htmlspecialchars($title); ?>" required />
      <input type="text" name="genre" id="genre" placeholder="Movie Genre" value="<?php echo htmlspecialchars($genre); ?>" required />
      <input type="file" name="video" id="videoFile" />
      <input type="file" name="subtitle" id="subtitleFile" />
      <input type="submit" value="<?php echo $id ? 'Update Movie' : 'Upload Movie'; ?>" />
    </form>

    <div id="messages" style="margin-top:1rem; font-weight: bold;"></div>
    <progress id="progressBar" value="0" max="100" style="width: 100%; display:none;"></progress>
  </div>
</body>

</html>

<script>
    const uploadForm = document.getElementById('uploadForm');
    const progressBar = document.getElementById('progressBar');
    const messages = document.getElementById('messages');

    const MAX_CHUNK_SIZE = 3 * 1024 * 1024;

    function setStatus(text, percent = null) {
      messages.textContent = text;
      if (percent !== null) {
        progressBar.style.display = 'block';
        progressBar.value = percent;
      }
    }

    uploadForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const title = document.getElementById('title').value.trim();
      const genre = document.getElementById('genre').value.trim();
      const videoInput = document.getElementById('videoFile');
      const subtitleInput = document.getElementById('subtitleFile');
      const id = document.getElementById('movieId').value;

      const videoFile = videoInput.files[0];
      const subtitleFile = subtitleInput.files[0];

      if (!title || !genre) {
        setStatus('Title and genre are required.');
        return;
      }

      // If no video, just upload data + subtitle
      if (!videoFile) {
        const formData = new FormData();
        formData.append('title', title);
        formData.append('genre', genre);
        if (id) formData.append('id', id);
        if (subtitleFile) formData.append('subtitle', subtitleFile);

        setStatus("Uploading metadata and subtitle...");
        const response = await fetch('upload_handle.php', {
          method: 'POST',
          body: formData,
        });
        const result = await response.json();
        setStatus(result.message || result.error || 'Upload complete!');
        return;
      }

      // Start chunked upload
      const totalChunks = Math.ceil(videoFile.size / MAX_CHUNK_SIZE);
      const fileName = videoFile.name;

      setStatus("Calculating chunks...", 0);

      for (let chunkNumber = 1; chunkNumber <= totalChunks; chunkNumber++) {
        const start = (chunkNumber - 1) * MAX_CHUNK_SIZE;
        const end = Math.min(start + MAX_CHUNK_SIZE, videoFile.size);
        const chunk = videoFile.slice(start, end);

        const formData = new FormData();
        formData.append('title', title);
        formData.append('genre', genre);
        formData.append('fileName', fileName);
        formData.append('chunkNumber', chunkNumber);
        formData.append('totalChunks', totalChunks);
        formData.append('video', chunk);
        if (id) formData.append('id', id);
        if (subtitleFile && chunkNumber === totalChunks) {
          formData.append('subtitle', subtitleFile);
        }

        setStatus(`Uploading chunk ${chunkNumber} of ${totalChunks}...`, Math.round((chunkNumber / totalChunks) * 80));

        const response = await fetch('upload_handle.php', {
          method: 'POST',
          body: formData,
        });

        const result = await response.json();
        if (!response.ok || result.error) {
          setStatus(result.error || 'Error uploading chunk.');
          return;
        }
      }

      setStatus("Merging chunks on server...", 90);

      const mergeForm = new FormData();
      mergeForm.append('fileName', fileName);
      mergeForm.append('finalize', true); // optional flag for backend to finalize

      await fetch('merge_chunks.php', {
        method: 'POST',
        body: mergeForm
      });

      if (subtitleFile) {
        setStatus("Uploading subtitle...", 95);
        const subtitleForm = new FormData();
        subtitleForm.append('subtitle', subtitleFile);
        subtitleForm.append('fileName', fileName);
        if (id) subtitleForm.append('id', id);

        await fetch('upload_subtitle.php', {
          method: 'POST',
          body: subtitleForm
        });
        setStatus("Subtitle upload complete!", 98);
      }

      setStatus("✅ Upload Success!", 100);
    });
</script>