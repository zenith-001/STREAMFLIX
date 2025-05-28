<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

include '../db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $title = $_POST['title'] ?? '';
    $genre = $_POST['genre'] ?? '';

    // Basic validation
    if (!$title || !$genre) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and genre are required']);
        exit;
    }

    // Handle video upload with chunking
    $videoPath = null;
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $videoFile = $_FILES['video'];
        $videoPath = '../uploads/videos/' . uniqid() . '_' . basename($videoFile['name']);
        if (!move_uploaded_file($videoFile['tmp_name'], $videoPath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move video file']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Video file is required']);
        exit;
    }

    // Handle subtitle upload
    $subtitlePath = null;
    if (isset($_FILES['subtitle']) && $_FILES['subtitle']['error'] === UPLOAD_ERR_OK) {
        $subtitleFile = $_FILES['subtitle'];
        $subtitlePath = '../uploads/subtitles/' . uniqid() . '_' . basename($subtitleFile['name']);
        if (!move_uploaded_file($subtitleFile['tmp_name'], $subtitlePath)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to move subtitle file']);
            exit;
        }
    }

    // Insert movie details into the database
    $query = "INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssss', $title, $genre, $videoPath, $subtitlePath);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        echo json_encode(['success' => true, 'message' => 'Movie uploaded successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to upload movie: ' . mysqli_error($conn)]);
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
        <h1>Upload New Movie</h1>
        <form id="uploadForm" enctype="multipart/form-data" method="POST">
            <input type="text" name="title" placeholder="Movie Title" required />
            <input type="text" name="genre" placeholder="Movie Genre" required />
            <input type="file" name="video" required />
            <input type="file" name="subtitle" />
            <input type="submit" value="Upload Movie" />
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

    const MAX_CHUNK_SIZE = 3 * 1024 * 1024; // 3MB

    function setStatus(text, percent = null) {
        messages.textContent = text;
        if (percent !== null) {
            progressBar.style.display = 'block';
            progressBar.value = percent;
        }
    }

    uploadForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const title = document.querySelector('input[name="title"]').value.trim();
        const genre = document.querySelector('input[name="genre"]').value.trim();
        const videoInput = document.querySelector('input[name="video"]');
        const subtitleInput = document.querySelector('input[name="subtitle"]');

        const videoFile = videoInput.files[0];
        const subtitleFile = subtitleInput.files[0];

        if (!title || !genre) {
            setStatus('Title and genre are required.');
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

        setStatus("✅ Upload Success!", 100);
    });
</script>
