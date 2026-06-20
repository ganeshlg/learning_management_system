<?php

require_once __DIR__ . '/../models/Admin.php';

class AdminController
{
    private Admin $admin;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->admin = new Admin($db);
        $this->db = $db;
    }

    public function login()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'email and password required']);
            return;
        }

        $admin = $this->admin->findByEmail($data['email']);
        if (!$admin || !password_verify($data['password'], $admin['password'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Invalid credentials']);
            return;
        }

        // update last_logged_in
        try {
            $stmt = $this->db->prepare('UPDATE admins SET last_logged_in = NOW() WHERE id = :id');
            $stmt->execute(['id' => $admin['id']]);
        } catch (PDOException $e) {
            // ignore update failure for login
        }

        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Login successful',
            'id' => $admin['id'],
            'role' => $admin['role'],
            'email' => $admin['email'],
            'name' => $admin['name'] ?? null,
            'lastLogin' => $admin['last_logged_in'] ?? null
        ]);
    }
    
    public function createTrainer()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['trainer_email']) || empty($data['trainer_password'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password, trainer_email and trainer_password required']);
            return;
        }

        // verify requesting admin is super_admin
        $admin = $this->admin->findByEmail($data['admin_email']);
        if (!$admin || !password_verify($data['admin_password'], $admin['password']) || $admin['role'] !== 'super_admin') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $name = $data['trainer_name'] ?? null;
        $ok = $this->admin->create($data['trainer_email'], $data['trainer_password'], 'trainer', $name);
        if (!$ok) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not create trainer (maybe already exists)']);
            return;
        }

        $trainer = $this->admin->findByEmail($data['trainer_email']);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Trainer created',
            'id' => $trainer['id'],
            'role' => $trainer['role'],
            'email' => $trainer['email'],
            'name' => $trainer['name'] ?? null,
            'lastLogin' => $trainer['last_logged_in'] ?? null
        ]);
    }

    public function listTrainers()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        // allow credentials via body or query for convenience
        $adminEmail = $data['admin_email'] ?? ($_GET['admin_email'] ?? null);
        $adminPassword = $data['admin_password'] ?? ($_GET['admin_password'] ?? null);

        if (empty($adminEmail) || empty($adminPassword)) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email and admin_password required']);
            return;
        }

        $admin = $this->admin->findByEmail($adminEmail);
        if (!$admin || !password_verify($adminPassword, $admin['password']) || $admin['role'] !== 'super_admin') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $trainers = $this->admin->listByRole('trainer');
        header('Content-Type: application/json');
        echo json_encode(['trainers' => $trainers]);
    }

    public function editTrainer()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['trainer_email'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and trainer_email required']);
            return;
        }

        $admin = $this->admin->findByEmail($data['admin_email']);
        if (!$admin || !password_verify($data['admin_password'], $admin['password']) || $admin['role'] !== 'super_admin') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $fields = [];
        if (isset($data['trainer_name'])) $fields['name'] = $data['trainer_name'];
        if (isset($data['trainer_password'])) $fields['password'] = $data['trainer_password'];

        if (empty($fields)) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'No fields to update']);
            return;
        }

        $ok = $this->admin->updateByEmail($data['trainer_email'], $fields);
        if (!$ok) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not update trainer']);
            return;
        }

        $trainer = $this->admin->findByEmail($data['trainer_email']);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Trainer updated',
            'id' => $trainer['id'],
            'role' => $trainer['role'],
            'email' => $trainer['email'],
            'name' => $trainer['name'] ?? null,
            'lastLogin' => $trainer['last_logged_in'] ?? null
        ]);
    }

    public function deleteTrainer()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['trainer_email'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and trainer_email required']);
            return;
        }

        $admin = $this->admin->findByEmail($data['admin_email']);
        if (!$admin || !password_verify($data['admin_password'], $admin['password']) || $admin['role'] !== 'super_admin') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $ok = $this->admin->deleteByEmail($data['trainer_email']);
        if (!$ok) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not delete trainer']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Trainer deleted']);
    }
}
