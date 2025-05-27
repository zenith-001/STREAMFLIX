<?php
// convert_existing_uploads.php
// Run this script ONCE from the browser or CLI after placing it in ENGLISH/admin/
// It will convert all .mp4 to HLS and all .srt to .vtt, and update the DB accordingly.

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../db.php';
$uploadDir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;

function log_convert($msg) {
    file_put_contents(__DIR__ . '/../uploads/convert_log.txt', date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
}

// 1. Convert all .mp4 to HLS
$mp4s = glob($uploadDir . '*.mp4');
foreach ($mp4s as $mp4) {
    $base = pathinfo($mp4, PATHINFO_FILENAME);
    $hlsDir = $uploadDir . $base . '_hls' . DIRECTORY_SEPARATOR;
    if (!is_dir($hlsDir)) mkdir($hlsDir, 0777, true);
    $m3u8 = $hlsDir . 'index.m3u8';
    if (!file_exists($m3u8)) {
        $cmd = 'ffmpeg -i ' . escapeshellarg($mp4) . ' -c:v copy -c:a copy -hls_time 10 -hls_list_size 0 -f hls ' . escapeshellarg($m3u8) . ' 2>&1';
        $out = shell_exec($cmd);
        if (file_exists($m3u8)) {
            log_convert("SUCCESS: $mp4 -> $m3u8");
        } else {
            log_convert("ERROR: $mp4\n$out");
        }
    }
}

// 2. Convert all .srt to .vtt
$srts = glob($uploadDir . '*.srt');
foreach ($srts as $srt) {
    $vtt = preg_replace('/\.srt$/i', '.vtt', $srt);
    if (!file_exists($vtt)) {
        $cmd = 'ffmpeg -i ' . escapeshellarg($srt) . ' ' . escapeshellarg($vtt) . ' 2>&1';
        $out = shell_exec($cmd);
        if (file_exists($vtt)) {
            log_convert("SUCCESS: $srt -> $vtt");
        } else {
            log_convert("ERROR: $srt\n$out");
        }
    }
}

// 3. Update DB for all movies
$sql = "SELECT id, video, subtitle FROM movies";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $video = $row['video'];
    $subtitle = $row['subtitle'];
    $newVideo = $video;
    $newSubtitle = $subtitle;
    if (preg_match('/([^\/\\]+)\.mp4$/i', $video, $m)) {
        $base = $m[1];
        $hlsPath = 'uploads/' . $base . '_hls/index.m3u8';
        if (file_exists($uploadDir . $base . '_hls' . DIRECTORY_SEPARATOR . 'index.m3u8')) {
            $newVideo = $hlsPath;
        }
    }
    if (preg_match('/\.srt$/i', $subtitle)) {
        $newSubtitle = preg_replace('/\.srt$/i', '.vtt', $subtitle);
        if (!file_exists($uploadDir . basename($newSubtitle))) {
            $newSubtitle = $subtitle; // fallback if vtt not found
        }
    }
    if ($newVideo !== $video || $newSubtitle !== $subtitle) {
        $stmt = $conn->prepare("UPDATE movies SET video=?, subtitle=? WHERE id=?");
        $stmt->bind_param('ssi', $newVideo, $newSubtitle, $id);
        $stmt->execute();
        $stmt->close();
        log_convert("DB UPDATE: id=$id video=$newVideo subtitle=$newSubtitle");
    }
}
$conn->close();

echo "Done. Check uploads/convert_log.txt for details.";
