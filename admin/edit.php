<?php
session_start();
ob_start(); // Start output buffering

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

include '../db.php';

// Initialize variables
$id = null;
$title = '';
$genre = '';
$subtitlePath = '';

// If editing, get existing movie data
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $movie = $result->fetch_assoc();
        $title = $movie['title'];
        $genre = $movie['genre'];
        $subtitlePath = $movie['subtitle'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Movie</title>
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
        <h1>Edit Movie</h1>
        <form id="editForm" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="id" value="<?php echo $id; ?>" />
            <input type="text" name="title" placeholder="Movie Title" value="<?php echo htmlspecialchars($title); ?>" required />
            <input type="text" name="genre" placeholder="Movie Genre" value="<?php echo htmlspecialchars($genre); ?>" required />
            <input type="file" name="subtitle" />
            <input type="submit" value="Update Movie" />
        </form>
        <div id="messages" style="margin-top:1rem; font-weight: bold;"></div>
    </div>
</body>

</html>

<script>
    const editForm = document.getElementById('editForm');
    const messages = document.getElementById('messages');

    editForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = new FormData(editForm);

        const response = await fetch('edit_handle.php', {
            method: 'POST',
            body: formData,
        });

        const result = await response.json();
        console.log(result);

        if (response.ok) {
            messages.textContent = result.message; // Display success message
        } else {
            messages.textContent = result.error || 'Error updating movie.';
        }
    });
</script>
