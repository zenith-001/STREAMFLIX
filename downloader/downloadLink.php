<?php
$file = basename($_GET['file'] ?? '');

$path = __DIR__ . '/tmp_download/' . $file;

if (!$file || !file_exists($path)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

// Send proper headers to download
header('Content-Type: video/mp4');
header('Content-Disposition: attachment; filename="movie.mp4"');
header('Content-Length: ' . filesize($path));
readfile($path);
