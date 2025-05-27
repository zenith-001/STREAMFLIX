<?php
// convert_existing_uploads.php
// UI version: process one file per request, show progress and allow clicking next.
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../db.php';
$uploadDir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;

function log_convert($msg) {
    file_put_contents(__DIR__ . '/../uploads/convert_log.txt', date('Y-m-d H:i:s') . " $msg\n", FILE_APPEND);
}

$step = $_GET['step'] ?? '';
$file = $_GET['file'] ?? '';
$done = false;
$message = '';

if ($step === 'video' && $file) {
    $mp4 = $uploadDir . basename($file);
    $base = pathinfo($mp4, PATHINFO_FILENAME);
    $hlsDir = $uploadDir . $base . '_hls' . DIRECTORY_SEPARATOR;
    if (!is_dir($hlsDir)) mkdir($hlsDir, 0777, true);
    $m3u8 = $hlsDir . 'index.m3u8';
    if (!file_exists($m3u8)) {
        $cmd = 'ffmpeg -i ' . escapeshellarg($mp4) . ' -c:v copy -c:a copy -hls_time 10 -hls_list_size 0 -f hls ' . escapeshellarg($m3u8) . ' 2>&1';
        $out = shell_exec($cmd);
        if (file_exists($m3u8)) {
            log_convert("SUCCESS: $mp4 -> $m3u8");
            $message = "<span style='color:green'>SUCCESS: $mp4 → $m3u8</span>";
        } else {
            log_convert("ERROR: $mp4\n$out");
            $message = "<span style='color:red'>ERROR: $mp4<br><pre>$out</pre></span>";
        }
    } else {
        $message = "Already converted: $mp4";
    }
    $done = true;
}

if ($step === 'subtitle' && $file) {
    $srt = $uploadDir . basename($file);
    $vtt = preg_replace('/\.srt$/i', '.vtt', $srt);
    if (!file_exists($vtt)) {
        $cmd = 'ffmpeg -i ' . escapeshellarg($srt) . ' ' . escapeshellarg($vtt) . ' 2>&1';
        $out = shell_exec($cmd);
        if (file_exists($vtt)) {
            log_convert("SUCCESS: $srt -> $vtt");
            $message = "<span style='color:green'>SUCCESS: $srt → $vtt</span>";
        } else {
            log_convert("ERROR: $srt\n$out");
            $message = "<span style='color:red'>ERROR: $srt<br><pre>$out</pre></span>";
        }
    } else {
        $message = "Already converted: $srt";
    }
    $done = true;
}

if ($step === 'db') {
    $sql = "SELECT id, video, subtitle FROM movies";
    $res = $conn->query($sql);
    $dbUpdates = 0;
    while ($row = $res->fetch_assoc()) {
        $id = $row['id'];
        $video = $row['video'];
        $subtitle = $row['subtitle'];
        $newVideo = $video;
        $newSubtitle = $subtitle;
        if (!empty($video) && preg_match('/([^\/\\]+)\.mp4$/i', $video, $m)) {
            $base = $m[1];
            $hlsPath = 'uploads/' . $base . '_hls/index.m3u8';
            if (file_exists($uploadDir . $base . '_hls' . DIRECTORY_SEPARATOR . 'index.m3u8')) {
                $newVideo = $hlsPath;
            }
        }
        if (!empty($subtitle) && preg_match('/\.srt$/i', $subtitle)) {
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
            $dbUpdates++;
        }
    }
    $conn->close();
    $message = "<span style='color:green'>DB update done. $dbUpdates row(s) updated.</span>";
    $done = true;
}

// List files to process
$pendingVideos = array_filter(glob($uploadDir . '*.mp4'), function($mp4) use ($uploadDir) {
    $base = pathinfo($mp4, PATHINFO_FILENAME);
    $hlsDir = $uploadDir . $base . '_hls' . DIRECTORY_SEPARATOR;
    $m3u8 = $hlsDir . 'index.m3u8';
    return !file_exists($m3u8);
});
$pendingSubtitles = array_filter(glob($uploadDir . '*.srt'), function($srt) {
    $vtt = preg_replace('/\.srt$/i', '.vtt', $srt);
    return !file_exists($vtt);
});

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Convert Progress</title>
    <style>
        body { font-family: Arial, sans-serif; background: #181818; color: #eee; padding: 2em; }
        .progress { margin: 1em 0; }
        .done { color: #0f0; }
        .error { color: #f33; }
        .pending { color: #ff0; }
        .button { background: #222; color: #fff; border: 1px solid #444; padding: 0.5em 1.2em; border-radius: 4px; cursor: pointer; margin: 0.2em 0; }
        .button:hover { background: #444; }
        pre { background: #222; color: #fff; padding: 1em; border-radius: 4px; }
    </style>
</head>
<body>
    <h2>Batch Conversion Progress</h2>
    <?php if ($message) echo '<div class="progress">' . $message . '</div>'; ?>
    <h3>Pending Video Files (<?php echo count($pendingVideos); ?>)</h3>
    <?php if ($pendingVideos) {
        $next = basename(reset($pendingVideos));
        echo "<form method='get'><input type='hidden' name='step' value='video'><input type='hidden' name='file' value='$next'><button class='button' type='submit'>Convert Next Video: $next</button></form>";
        echo "<ul>";
        foreach ($pendingVideos as $v) echo "<li class='pending'>" . htmlspecialchars(basename($v)) . "</li>";
        echo "</ul>";
    } else {
        echo "<div class='done'>All videos converted!</div>";
    }
    ?>
    <h3>Pending Subtitle Files (<?php echo count($pendingSubtitles); ?>)</h3>
    <?php if ($pendingSubtitles) {
        $next = basename(reset($pendingSubtitles));
        echo "<form method='get'><input type='hidden' name='step' value='subtitle'><input type='hidden' name='file' value='$next'><button class='button' type='submit'>Convert Next Subtitle: $next</button></form>";
        echo "<ul>";
        foreach ($pendingSubtitles as $s) echo "<li class='pending'>" . htmlspecialchars(basename($s)) . "</li>";
        echo "</ul>";
    } else {
        echo "<div class='done'>All subtitles converted!</div>";
    }
    ?>
    <h3>Database Update</h3>
    <form method="get">
        <input type="hidden" name="step" value="db">
        <button class="button" type="submit">Update Database Links</button>
    </form>
    <hr>
    <h4>Log Preview (last 20 lines):</h4>
    <pre><?php
    $log = @file(__DIR__ . '/../uploads/convert_log.txt');
    if ($log) echo htmlspecialchars(implode('', array_slice($log, -20)));
    ?></pre>
</body>
</html>
