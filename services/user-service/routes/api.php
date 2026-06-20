<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CourseController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/AdminController.php';

$db = (new Database())->connect();

$authController = new AuthController($db);
$courseController = new CourseController($db);

$uri = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);

$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/register' && $method === 'POST') {
    $authController->register();
    exit;
}

if ($uri === '/login' && $method === 'POST') {
    $authController->login();
    exit;
}

if ($uri === '/courses' && $method === 'GET') {
    $courseController->listCourses();
    exit;
}

if (preg_match('#^/courses/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    $courseController->getCourse($matches[1]);
    exit;
}

// ADMIN LOGIN
if ($uri === '/admin/login' && $method === 'POST') {
    $adminController = new AdminController($db);
    $adminController->login();
    exit;
}

// ADMIN: add trainer
if ($uri === '/admin/trainers' && $method === 'POST') {
    $adminController = new AdminController($db);
    $adminController->createTrainer();
    exit;
}

// ADMIN: list trainers
if ($uri === '/admin/trainers' && $method === 'GET') {
    $adminController = new AdminController($db);
    $adminController->listTrainers();
    exit;
}

// ADMIN: edit trainer
if ($uri === '/admin/trainers' && $method === 'PUT') {
    $adminController = new AdminController($db);
    $adminController->editTrainer();
    exit;
}

// ADMIN: delete trainer
if ($uri === '/admin/trainers' && $method === 'DELETE') {
    $adminController = new AdminController($db);
    $adminController->deleteTrainer();
    exit;
}

// ADMIN: create course/module/lesson
if ($uri === '/admin/courses' && $method === 'POST') {
    $courseController->createCourse();
    exit;
}

if ($uri === '/admin/modules' && $method === 'POST') {
    $courseController->createModule();
    exit;
}

if ($uri === '/admin/lessons' && $method === 'POST') {
    $courseController->createLesson();
    exit;
}

// PURCHASE COURSE
if ($uri === '/purchase' && $method === 'POST') {
    $courseController->purchaseCourse();
    exit;
}

// GET PURCHASED COURSE IDS BY USER EMAIL
if ($uri === '/purchases' && $method === 'GET') {
    $courseController->getUserPurchases();
    exit;
}

http_response_code(404);

echo json_encode([
    "message" => "Route not found"
]);