<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

include '../db.php';

$editing = false;
$movie = [
    'title' => '',
    'genre' => '',
    'video' => '',
    'subtitle' => '',
];

if (isset($_GET['id'])) {
    $editing = true;
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $movie = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $genre = $_POST['genre'];

    $videoPath = $editing ? $movie['video'] : '';
    $subtitlePath = $editing ? $movie['subtitle'] : '';

    if (isset($_FILES["video"]) && $_FILES["video"]["error"] == 0) {
        if ($editing && file_exists($movie['video'])) {
            unlink($movie['video']);
        }
        $videoPath = "uploads/videos/" . basename($_FILES["video"]["name"]);
        move_uploaded_file($_FILES["video"]["tmp_name"], $videoPath);
    }

    if (isset($_FILES["subtitle"]) && $_FILES["subtitle"]["error"] == 0) {
        if ($editing && !empty($movie['subtitle']) && file_exists($movie['subtitle'])) {
            unlink($movie['subtitle']);
        }
        $subtitlePath = "uploads/subtitles/" . basename($_FILES["subtitle"]["name"]);
        move_uploaded_file($_FILES["subtitle"]["tmp_name"], $subtitlePath);
    }

    if ($editing) {
        $stmt = $conn->prepare("UPDATE movies SET title=?, genre=?, video=?, subtitle=? WHERE id=?");
        $stmt->bind_param("ssssi", $title, $genre, $videoPath, $subtitlePath, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO movies (title, genre, video, subtitle) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $genre, $videoPath, $subtitlePath);
    }

    if ($stmt->execute()) {
        header("Location: monitor.php");
        exit;
    } else {
        echo "Error saving movie.";
    }

    $stmt->close();
    $conn->close();
}
?>
