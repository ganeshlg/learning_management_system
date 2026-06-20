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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        http_response_code(502);
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
// VIDEO STREAM PROXY
// ================================
function proxyVideo($url)
{
    $ch = curl_init();
    $headers = [];

    if (!empty($_SERVER['HTTP_RANGE'])) {
        $headers[] = "Range: " . $_SERVER['HTTP_RANGE'];
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            "message" => "Video Gateway Error",
            "error" => curl_error($ch)
        ]);
        return;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $rawHeaders = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    http_response_code($statusCode);

    foreach (explode("\r\n", $rawHeaders) as $headerLine) {
        $trim = trim($headerLine);
        if ($trim === '' || stripos($trim, 'HTTP/') === 0) {
            continue;
        }
        if (stripos($trim, 'Transfer-Encoding:') === 0 || stripos($trim, 'Connection:') === 0 || stripos($trim, 'Host:') === 0) {
            continue;
        }
        header($trim, false);
    }

    echo $body;
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

// GET ALL COURSES
if ($uri === '/api/courses' && $method === 'GET') {
    proxyRequest($services['user_service'] . '/courses');
    exit;
}

// GET SINGLE COURSE WITH MODULES AND LESSONS
if (preg_match('#^/api/courses/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    proxyRequest($services['user_service'] . '/courses/' . urlencode($matches[1]));
    exit;
}

// ADMIN LOGIN (proxy)
if ($uri === '/api/admin/login' && $method === 'POST') {
    proxyRequest($services['user_service'] . '/admin/login');
    exit;
}

// ADMIN: add trainer (proxy)
if ($uri === '/api/admin/trainers' && $method === 'POST') {
    proxyRequest($services['user_service'] . '/admin/trainers');
    exit;
}

if ($uri === '/api/admin/trainers' && $method === 'GET') {
    // forward query string for credentials
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $target = $services['user_service'] . '/admin/trainers' . ($qs ? '?' . $qs : '');
    proxyRequest($target);
    exit;
}

if ($uri === '/api/admin/trainers' && $method === 'PUT') {
    proxyRequest($services['user_service'] . '/admin/trainers');
    exit;
}

if ($uri === '/api/admin/trainers' && $method === 'DELETE') {
    proxyRequest($services['user_service'] . '/admin/trainers');
    exit;
}

// PURCHASE COURSE (create purchase)
if ($uri === '/api/purchase' && $method === 'POST') {
    proxyRequest($services['user_service'] . '/purchase');
    exit;
}

// GET PURCHASED COURSE IDS (query param: email)
if ($uri === '/api/purchases' && $method === 'GET') {
    // forward query string
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $target = $services['user_service'] . '/purchases' . ($qs ? '?' . $qs : '');
    proxyRequest($target);
    exit;
}

// VIDEO STREAM
if (($method === 'GET' || $method === 'HEAD') && preg_match('#^/api/videos/(.+)$#', $uri, $matches)) {

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