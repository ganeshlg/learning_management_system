<?php

// ================================
// CORS HEADERS
// ================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, Range");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================================
// CONFIG
// ================================
$services = require __DIR__ . '/../config/services.php';

// ================================
// REQUEST INFO
// ================================
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];


// ================================
// HELPERS
// ================================
function getForwardHeaders($contentType = 'application/json')
{
    $headers = [];

    if ($contentType) {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') !== 0) {
            continue;
        }

        $header = str_replace('_', '-', substr($key, 5));
        if (in_array(strtolower($header), ['host', 'content-length', 'connection', 'keep-alive', 'proxy-authenticate', 'proxy-authorization', 'te', 'trailers', 'transfer-encoding', 'upgrade'], true)) {
            continue;
        }

        $headers[] = $header . ': ' . $value;
    }

    return $headers;
}

function probeService($url)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'HEAD',
        CURLOPT_NOBODY => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    curl_exec($ch);

    return [
        'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'error' => curl_error($ch)
    ];
}


// ================================
// JSON PROXY
// ================================
function proxyRequest($serviceUrl)
{
    $payload = file_get_contents("php://input");
    $headers = getForwardHeaders('application/json');

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $serviceUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload ?: null,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        http_response_code(500);
        echo json_encode([
            "message" => "Gateway Error",
            "error" => curl_error($ch)
        ]);
        return;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    http_response_code($statusCode);
    header("Content-Type: " . ($contentType ?: "application/json"));

    echo $response;
}


// ================================
// VIDEO STREAM PROXY (FIXED)
// ================================
function proxyVideo($url)
{
    $ch = curl_init();
    $headers = [];

    if (!empty($_SERVER['HTTP_RANGE'])) {
        $headers[] = "Range: " . $_SERVER['HTTP_RANGE'];
    }

    $headersSent = false;

    // Capture and forward response headers before body arrives
    $headerFunction = function ($ch, $headerLine) use (&$headersSent) {
        $trim = trim($headerLine);
        if ($trim === '') {
            return strlen($headerLine);
        }

        // Status line (e.g. HTTP/1.1 206 Partial Content)
        if (preg_match('#^HTTP/\d+\.\d+\s+(\d+)#i', $trim, $m)) {
            $code = (int)$m[1];
            http_response_code($code);
            return strlen($headerLine);
        }

        // Forward other headers (skip hop-by-hop and irrelevant headers)
        if (stripos($trim, 'Transfer-Encoding:') === 0 || stripos($trim, 'Connection:') === 0 || stripos($trim, 'Host:') === 0) {
            return strlen($headerLine);
        }

        // Replace Content-Type/Length to ensure client sees the correct values
        if (stripos($trim, 'Content-Type:') === 0 || stripos($trim, 'Content-Length:') === 0 || stripos($trim, 'Content-Range:') === 0) {
            header($trim, true);
        } else {
            header($trim, false);
        }
        return strlen($headerLine);
    };

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => $headerFunction,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            flush();
            return strlen($data);
        }
    ]);

    $success = curl_exec($ch);

    if ($success === false) {
        http_response_code(500);
        echo json_encode([
            "message" => "Video Gateway Error",
            "error" => curl_error($ch)
        ]);
        return;
    }
}


// ================================
// ROUTES
// ================================

// REGISTER
if ($uri === '/api/register' && $method === 'POST') {
    proxyRequest($services['user_service'] . '/register');
    exit;
}

// LOGIN
if ($uri === '/api/login' && $method === 'POST') {
    proxyRequest($services['user_service'] . '/login');
    exit;
}

// DEBUG
if ($uri === '/api/debug' && $method === 'GET') {
    $userProbe = probeService($services['user_service']);
    $videoProbe = probeService($services['video_service'] . '/videos/civil.mp4');

    header('Content-Type: application/json');
    echo json_encode([
        'user_service' => $services['user_service'],
        'user_probe' => $userProbe,
        'video_service' => $services['video_service'],
        'video_probe' => $videoProbe,
        'video_test_url' => $services['video_service'] . '/videos/civil.mp4'
    ]);
    exit;
}

// VIDEO STREAM
if ($method === 'GET' && preg_match('#^/api/videos/(.+)$#', $uri, $matches)) {

    $videoFile = rawurlencode($matches[1]);

    proxyVideo(
        $services['video_service'] . '/videos/' . $videoFile
    );

    exit;
}


// ================================
// 404
// ================================
http_response_code(404);
header('Content-Type: application/json');

echo json_encode([
    "message" => "Gateway route not found"
]);