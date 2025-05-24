<?php
session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php'); // Redirect to login page
    exit;
}

// Include database connection
include '../db.php';

// Fetch movies from the database
$query = "SELECT * FROM movies"; // Adjust the table name as necessary
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin - Monitor Movies</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #121212;
            color: #e0e0e0;
            padding: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #444;
        }

        th {
            background: #1f1f1f;
        }

        .button {
            background: #e50914;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .button:hover {
            background: #d40813;
        }
    </style>
</head>

<body>
    <h1>Monitor Movies</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Genre</th>
                <th>Video File</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($movie = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?php echo $movie['id']; ?></td>
                    <td><?php echo $movie['title']; ?></td>
                    <td><?php echo $movie['genre']; ?></td>
                    <td><?php echo $movie['video']; ?></td>
                    <td>
                        <form action="upload.php" method="GET" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $movie['id']; ?>" />
                            <button type="submit" class="button">Edit</button>
                        </form>
                        <form action="delete_movie.php" method="POST" style="display:inline;">
                            <input type="hidden" name="id" value="<?php echo $movie['id']; ?>" />
                            <button type="submit" class="button">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>

</html>