<?php
// Move all .vtt subtitle files in uploads/ to their respective HLS folder by matching the id prefix
$uploadDir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;
$files = glob($uploadDir . '*.vtt');
$hlsFolders = glob($uploadDir . '*_hls', GLOB_ONLYDIR);
$count = 0;

foreach ($files as $vttPath) {
    $filename = basename($vttPath);
    // Extract id prefix (before first underscore)
    if (preg_match('/^([a-f0-9]+)_.*\.vtt$/i', $filename, $m)) {
        $id = $m[1];
        $found = false;
        foreach ($hlsFolders as $hlsDir) {
            if (strpos(basename($hlsDir), $id . '_') === 0) {
                $dest = $hlsDir . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($dest)) {
                    if (rename($vttPath, $dest)) {
                        echo "Moved $filename to $hlsDir<br>";
                        $count++;
                    } else {
                        echo "Failed to move $filename to $hlsDir<br>";
                    }
                } else {
                    echo "$filename already exists in $hlsDir<br>";
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "No matching HLS folder for $filename<br>";
        }
    } else {
        echo "Could not parse id from $filename<br>";
    }
}
echo "<br>Done. $count file(s) moved.";
