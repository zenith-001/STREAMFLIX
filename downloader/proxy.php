<?php
// Allow requests from any origin (CORS header)
header("Access-Control-Allow-Origin: *");

// Get the URL param
$url = $_GET['url'] ?? '';

if (!$url) {
    http_response_code(400);
    echo "Missing url parameter";
    exit;
}

// Validate URL - simple check
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo "Invalid url parameter";
    exit;
}

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; StreamFlixProxy/1.0)');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);

if ($code !== 200) {
    http_response_code($code);
    echo "Failed to fetch remote file";
    exit;
}

// Set content type header based on fetched content
if ($contentType) {
    header("Content-Type: $contentType");
} else {
    header("Content-Type: application/octet-stream");
}

echo $response;
