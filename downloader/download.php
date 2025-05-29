<?php
set_time_limit(0);
header('Content-Type: application/json');

// Check input JSON body
$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing URL']);
    exit;
}

function curlGet($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; StreamFlixDownloader/1.0)',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);
    $data = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return false;
    return $data;
}

// Download playlist
$playlist = curlGet($url);
if (!$playlist) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch playlist']);
    exit;
}

// Save to temp directory
$tmpDir = __DIR__ . '/tmp_download';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0777, true);

// Parse playlist base URL
$baseUrl = substr($url, 0, strrpos($url, '/') + 1);

// Extract TS segment URLs
preg_match_all('/^(?!#)(.+\.ts)$/m', $playlist, $matches);
$segments = $matches[1] ?? [];

if (!$segments) {
    echo json_encode(['status' => 'error', 'message' => 'No TS segments found']);
    exit;
}

// Download all TS segments
$tsFiles = [];
foreach ($segments as $i => $segment) {
    $segmentUrl = (strpos($segment, 'http') === 0) ? $segment : $baseUrl . $segment;
    $tsContent = curlGet($segmentUrl);
    if (!$tsContent) {
        echo json_encode(['status' => 'error', 'message' => "Failed to download segment: $segment"]);
        exit;
    }
    $filePath = "$tmpDir/segment_$i.ts";
    file_put_contents($filePath, $tsContent);
    $tsFiles[] = $filePath;
}

// Merge TS segments into one file (simple concat)
$mergedTs = "$tmpDir/merged.ts";
file_put_contents($mergedTs, ''); // clear file
foreach ($tsFiles as $file) {
    file_put_contents($mergedTs, file_get_contents($file), FILE_APPEND);
}

// Convert merged.ts to MP4 with ffmpeg
$outputMp4 = "$tmpDir/movie_" . time() . ".mp4";
$ffmpegCmd = "ffmpeg -y -i " . escapeshellarg($mergedTs) . " -c copy " . escapeshellarg($outputMp4) . " 2>&1";
exec($ffmpegCmd, $output, $return_var);

if ($return_var !== 0 || !file_exists($outputMp4)) {
    echo json_encode(['status' => 'error', 'message' => 'FFmpeg conversion failed: ' . implode("\n", $output)]);
    exit;
}

// Clean up segment files
foreach ($tsFiles as $file) {
    @unlink($file);
}
@unlink($mergedTs);

// Return downloadable file link
$downloadLink = basename($outputMp4);

echo json_encode([
    'status' => 'success',
    'message' => 'Download and conversion complete',
    'downloadLink' => $downloadLink
]);
