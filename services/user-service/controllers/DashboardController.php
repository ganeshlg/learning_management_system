<?php

require_once __DIR__ . '/../models/Dashboard.php';
require_once __DIR__ . '/../models/Admin.php';

class DashboardController
{
    private Dashboard $dashboard;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->dashboard = new Dashboard($db);
    }

    private function authorizeAdmin($email, $password)
    {
        $admin = (new Admin($this->db))->findByEmail($email);
        if (!$admin) {
            return false;
        }

        return password_verify($password, $admin['password']) ? $admin : false;
    }

    private function requireAdminCredentials(): ?array
    {
        $email = $_GET['admin_email'] ?? null;
        $password = $_GET['admin_password'] ?? null;

        if (!$email || !$password) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email and admin_password are required']);
            return null;
        }

        $admin = $this->authorizeAdmin($email, $password);
        if (!$admin) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden']);
            return null;
        }

        return $admin;
    }

    public function getDashboard()
    {
        $summary = $this->dashboard->getTotals();
        $trend = $this->dashboard->getEnrollmentTrend(6);
        $recent = $this->dashboard->getRecentActivities(20);

        header('Content-Type: application/json');
        echo json_encode([
            'summary' => $summary,
            'enrollment_trend' => $trend,
            'recent_activities' => $recent,
        ]);
    }

    public function getSummary()
    {
        header('Content-Type: application/json');
        echo json_encode($this->dashboard->getTotals());
    }

    public function getEnrollmentTrend()
    {
        $months = isset($_GET['months']) ? (int) $_GET['months'] : 6;
        if ($months < 1) {
            $months = 6;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'months' => $months,
            'trend' => $this->dashboard->getEnrollmentTrend($months),
        ]);
    }

    public function getRecentActivities()
    {
        $admin = $this->requireAdminCredentials();
        if (!$admin) {
            return;
        }

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
        if ($limit < 1) {
            $limit = 20;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'limit' => $limit,
            'activities' => $this->dashboard->getRecentActivities($limit),
        ]);
    }

    public function createActivity()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $user = trim($data['user'] ?? '');
        $activity = trim($data['activity'] ?? '');
        if ($user === '' || $activity === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'user and activity are required']);
            return;
        }

        $ok = $this->dashboard->addActivity($user, $activity);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not save activity']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Activity logged']);
    }
}
