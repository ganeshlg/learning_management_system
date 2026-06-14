<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$db = (new Database())->connect();

$controller = new AuthController($db);

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/register' && $method === 'POST') {
    $controller->register();
    exit;
}

if ($uri === '/login' && $method === 'POST') {
    $controller->login();
    exit;
}

http_response_code(404);

echo json_encode([
    "message" => "Route not found"
]);