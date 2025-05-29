<?php
// Simple StreamFlix downloader single page

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? rrmdir("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
}

function fetchUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function downloadFile($url, $saveTo) {
    $fp = fopen($saveTo, 'w+');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

$message = '';
$downloadFile = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim($_POST['url'] ?? '');
    if (!$url) {
        $message = "Please enter a watch.php?id=... URL.";
    } else {
        $tmpDir = __DIR__ . "/tmp_streamflix_" . time();
        mkdir($tmpDir, 0777, true);

        $page = fetchUrl($url);
        if (!$page) {
            $message = "Failed to fetch the page.";
        } else {
            // Extract .m3u8 URL from the page
            if (!preg_match('/["\'](.*?\.m3u8)["\']/', $page, $match)) {
                $message = "No .m3u8 playlist found on page.";
            } else {
                $m3u8 = $match[1];
                // Make absolute URL if relative
                if (!preg_match('#^https?://#', $m3u8)) {
                    $base = substr($url, 0, strrpos($url, '/') + 1);
                    $m3u8 = $base . $m3u8;
                }

                // Fetch playlist content
                $playlist = fetchUrl($m3u8);
                if (!$playlist) {
                    $message = "Failed to fetch m3u8 playlist.";
                } else {
                    // Check if master playlist
                    if (strpos($playlist, '#EXT-X-STREAM-INF') !== false) {
                        // Get first variant playlist
                        if (!preg_match('/^(.*\.m3u8)$/m', $playlist, $varMatch)) {
                            $message = "No variant playlist found in master playlist.";
                        } else {
                            $variant = $varMatch[1];
                            if (!preg_match('#^https?://#', $variant)) {
                                $base = substr($m3u8, 0, strrpos($m3u8, '/') + 1);
                                $variant = $base . $variant;
                            }
                            $playlist = fetchUrl($variant);
                            if (!$playlist) {
                                $message = "Failed to fetch variant playlist.";
                            }
                            $m3u8 = $variant;
                        }
                    }
                }

                if (!$message) {
                    // Extract .ts segments
                    preg_match_all('/^(.*\.ts)$/m', $playlist, $tsMatches);
                    if (empty($tsMatches[1])) {
                        $message = "No TS segments found in playlist.";
                    } else {
                        $base = substr($m3u8, 0, strrpos($m3u8, '/') + 1);
                        $segmentFiles = [];
                        $counter = 0;
                        foreach ($tsMatches[1] as $segment) {
                            if (!preg_match('#^https?://#', $segment)) {
                                $segment = $base . $segment;
                            }
                            $savePath = "$tmpDir/segment_$counter.ts";
                            downloadFile($segment, $savePath);
                            $segmentFiles[] = $savePath;
                            $counter++;
                        }

                        // Create concat file for ffmpeg
                        $concatFile = "$tmpDir/concat.txt";
                        $f = fopen($concatFile, 'w');
                        foreach ($segmentFiles as $seg) {
                            fwrite($f, "file '" . str_replace("'", "'\\''", $seg) . "'\n");
                        }
                        fclose($f);

                        $outputFile = "$tmpDir/output.mp4";

                        // Run ffmpeg concat
                        $cmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($concatFile) . " -c copy " . escapeshellarg($outputFile) . " 2>&1";
                        exec($cmd, $output, $ret);

                        if ($ret !== 0) {
                            $message = "FFmpeg failed: " . implode("\n", $output);
                        } else {
                            $downloadFile = $outputFile;
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>StreamFlix Downloader</title>
    <style>
        body {
            background: #0f172a;
            color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            text-align: center;
            padding: 50px;
        }

        input[type="text"] {
            width: 80%;
            padding: 12px;
            border-radius: 10px;
            border: none;
            font-size: 16px;
        }

        button {
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(to right, #0ea5e9, #2563eb);
            color: white;
            cursor: pointer;
            margin-left: 10px;
        }

        a.download-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 18px;
            color: #10b981;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 10px;
            transition: background 0.3s ease;
        }

        a.download-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .message {
            margin-top: 20px;
            font-size: 16px;
            color: #f87171;
            white-space: pre-wrap;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
</head>

<body>
    <h1>🚀 StreamFlix Movie Downloader</h1>
    <form method="post">
        <input type="text" name="url" placeholder="Paste your watch.php?id=... URL here" value="<?= htmlspecialchars($_POST['url'] ?? '') ?>" />
        <button type="submit">Download</button>
    </form>

    <?php if ($message): ?>
        <div class="message"><?= nl2br(htmlspecialchars($message)) ?></div>
    <?php endif; ?>

    <?php if ($downloadFile): ?>
        <a class="download-link" href="<?= basename($downloadFile) ?>" download>⬇️ Download Movie.mp4</a>
        <script>
            // Auto-download after ready
            window.onload = () => {
                const link = document.querySelector('.download-link');
                if (link) {
                    link.click();
                }
            }
        </script>
    <?php endif; ?>
</body>

</html>
