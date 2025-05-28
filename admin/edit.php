<?php
session_start();
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


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // On form submission, override variables with POST data
    $id = $_POST['id'] ?? '';
    $title = $_POST['title'] ?? '';
    $genre = $_POST['genre'] ?? '';


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


    // Update movie details in the database
    $query = "UPDATE movies SET title = ?, genre = ?" . ($subtitlePath ? ", subtitle = ?" : "") . " WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);


    if ($subtitlePath) {
        mysqli_stmt_bind_param($stmt, 'sssi', $title, $genre, $subtitlePath, $id);
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
            <input type="text" name="title" placeholder="Movie Title" value="<?php echo htmlspecialchars($title); ?>"
                required />
            <input type="text" name="genre" placeholder="Movie Genre" value="<?php echo htmlspecialchars($genre); ?>"
                required />
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


        const response = await fetch('edit.php', {
            method: 'POST',
            body: formData,
        });


        const result = await response.json();
        if (response.ok) {
            messages.textContent = result.message;
        } else {
            messages.textContent = result.error || 'Error updating movie.';
        }
    });
</script>