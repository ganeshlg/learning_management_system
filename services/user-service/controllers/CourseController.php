<?php

require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/Purchase.php';

class CourseController
{
    private Course $course;
    private Purchase $purchase;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->course = new Course($db);
        $this->purchase = new Purchase($db);
    }

    private function authorizeAdmin($email, $password)
    {
        require_once __DIR__ . '/AdminController.php';
        require_once __DIR__ . '/../models/Admin.php';
        $admin = (new Admin($this->db))->findByEmail($email);
        if (!$admin) return false;
        return password_verify($password, $admin['password']) ? $admin : false;
    }

    public function createCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id']) || empty($data['title'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password, id and title required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || $admin['role'] !== 'super_admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $ok = $this->course->createCourse($data);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not create course']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Course created']);
    }

    public function createModule()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id']) || empty($data['course_id']) || empty($data['title'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password, id, course_id and title required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->createModule($data);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not create module']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Module created']);
    }

    public function createLesson()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id']) || empty($data['module_id']) || empty($data['title'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password, id, module_id and title required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->createLesson($data);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not create lesson']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Lesson created']);
    }

    public function listCourses()
    {
        $courses = $this->course->getAll();
        header('Content-Type: application/json');
        echo json_encode(['courses' => $courses]);
    }

    public function getCourse($courseId)
    {
        $course = $this->course->getCourseWithContent($courseId);
        if (!$course) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Course not found']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['course' => $course]);
    }

    public function purchaseCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['email']) || empty($data['course_id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'email and course_id required']);
            return;
        }

        require_once __DIR__ . '/../models/User.php';
        $userModel = new User($this->db);

        $user = $userModel->findByEmail($data['email']);
        if (!$user) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'User not found']);
            return;
        }

        $ok = $this->purchase->addPurchaseByUserId($user['id'], $data['course_id']);
        if (!$ok) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Already purchased or error']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Purchase recorded']);
    }

    public function getUserPurchases()
    {
        $email = $_GET['email'] ?? null;
        if (!$email) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'email query parameter required']);
            return;
        }

        require_once __DIR__ . '/../models/User.php';
        $userModel = new User($this->db);

        $user = $userModel->findByEmail($email);
        if (!$user) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'User not found']);
            return;
        }

        $courseIds = $this->purchase->getCourseIdsByUserId($user['id']);
        header('Content-Type: application/json');
        echo json_encode(['course_ids' => $courseIds]);
    }
}
