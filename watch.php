<?php
include 'db.php';

if (!isset($_GET['id'])) {
  echo "No movie selected.";
  exit;
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM movies WHERE id = $id";
$result = $conn->query($sql);

if ($result->num_rows === 0) {
  echo "Movie not found.";
  exit;
}

$movie = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($movie['title']); ?> - StreamFlix</title>
  <link rel="stylesheet" href="https://site-assets.fontawesome.com/releases/v6.7.2/css/duotone-light.css">
  <style>
    body {
      margin: 0;
      background-color: #121212;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    h1 {
      margin-bottom: 1rem;
    }

    video {
      max-width: 90%;
      border-radius: 12px;
      box-shadow: 0 0 16px #000000aa;
    }
  </style>
</head>

<body>
  <h1><?php echo htmlspecialchars($movie['title']); ?></h1>
  <video id="video" controls>
    <?php if (preg_match('/\.m3u8$/i', $movie['video'])): ?>
      <!-- HLS.js will handle the source -->
    <?php else: ?>
      <source src="ENGLISH/<?php echo htmlspecialchars($movie['video']); ?>" type="video/mp4">
    <?php endif; ?>
    <?php if (!empty($movie['subtitle'])): ?>
      <track src="ENGLISH/uploads/<?php echo htmlspecialchars($movie['subtitle']); ?>" kind="subtitles" srclang="en" label="English" default>
    <?php endif; ?>
    Your browser does not support the video tag.
  </video>

  <?php if (preg_match('/\.m3u8$/i', $movie['video'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
      const video = document.getElementById('video');
      if (Hls.isSupported()) {
        const hls = new Hls();
        hls.loadSource('ENGLISH/<?php echo htmlspecialchars($movie['video']); ?>');
        hls.attachMedia(video);
      } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
        video.src = 'ENGLISH/<?php echo htmlspecialchars($movie['video']); ?>';
      }
    </script>
  <?php endif; ?>
</body>

</html>