<?php

header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');

$uri = $_SERVER['REQUEST_URI'];

if (!preg_match('#/videos/(.+)$#', $uri, $matches)) {
    http_response_code(404);
    echo json_encode(['message' => 'Invalid video request']);
    exit;
}

$filename = basename($matches[1]);

$videoPath = __DIR__ . '/../storage/videos/' . $filename;

if (!file_exists($videoPath)) {
    http_response_code(404);
    echo json_encode(['message' => 'Video not found']);
    exit;
}

$size = filesize($videoPath);

$start = 0;
$end = $size - 1;

$fp = fopen($videoPath, 'rb');


// ================================
// HANDLE RANGE REQUEST
// ================================
if (isset($_SERVER['HTTP_RANGE'])) {

    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $range);

    $start = (int)$range[1];

    if (!empty($range[2])) {
        $end = (int)$range[2];
    }

    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;

header("Content-Length: $length");

fseek($fp, $start);

while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, 8192);
    flush();
}

fclose($fp);
exit;