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

    private function getRequestData()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = [];
        }
        if (empty($data) && !empty($_POST)) {
            $data = $_POST;
        }

        return $data;
    }

    private function normalizeTrainerProfile(array $data): array
    {
        $profile = [];

        if (array_key_exists('profile_description', $data)) {
            $value = trim((string) $data['profile_description']);
            if ($value !== '') {
                $profile['profile_description'] = $value;
            }
        }

        if (array_key_exists('experience_years', $data)) {
            $value = trim((string) $data['experience_years']);
            if ($value !== '') {
                $profile['experience_years'] = (int) $value;
            }
        }

        if (array_key_exists('expertise', $data)) {
            $value = $data['expertise'];
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('trim', $value)));
            } else {
                $value = trim((string) $value);
            }

            if ($value !== '') {
                $profile['expertise'] = $value;
            }
        }

        if (array_key_exists('phone', $data)) {
            $value = trim((string) $data['phone']);
            if ($value !== '') {
                $profile['phone'] = $value;
            }
        }

        if (array_key_exists('location', $data)) {
            $value = trim((string) $data['location']);
            if ($value !== '') {
                $profile['location'] = $value;
            }
        }

        if (array_key_exists('linkedin_url', $data)) {
            $value = trim((string) $data['linkedin_url']);
            if ($value !== '') {
                $profile['linkedin_url'] = $value;
            }
        }

        if (array_key_exists('website_url', $data)) {
            $value = trim((string) $data['website_url']);
            if ($value !== '') {
                $profile['website_url'] = $value;
            }
        }

        if (array_key_exists('photo_url', $data)) {
            $value = trim((string) $data['photo_url']);
            if ($value !== '') {
                $profile['photo_url'] = $value;
            }
        }

        if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
            $uploadedPhotoUrl = $this->uploadPhoto($_FILES['photo']);
            if ($uploadedPhotoUrl) {
                $profile['photo_url'] = $uploadedPhotoUrl;
            }
        }

        return $profile;
    }

    private function uploadPhoto(array $upload): ?string
    {
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($upload['name']));
        if ($safeName === '') {
            $safeName = 'trainer_photo';
        }

        $uploadDir = __DIR__ . '/../storage/uploads/trainers';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $safeName;
        if (file_exists($destination)) {
            $destination = $uploadDir . '/' . pathinfo($safeName, PATHINFO_FILENAME) . '_' . time() . '.' . pathinfo($safeName, PATHINFO_EXTENSION);
        }

        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            return null;
        }

        return 'uploads/trainers/' . basename($destination);
    }

    public function login()
    {
        $data = $this->getRequestData();
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
            'profileDescription' => $admin['profile_description'] ?? null,
            'experienceYears' => $admin['experience_years'] ?? null,
            'photoUrl' => $admin['photo_url'] ?? null,
            'expertise' => $admin['expertise'] ?? null,
            'phone' => $admin['phone'] ?? null,
            'location' => $admin['location'] ?? null,
            'linkedinUrl' => $admin['linkedin_url'] ?? null,
            'websiteUrl' => $admin['website_url'] ?? null,
            'lastLogin' => $admin['last_logged_in'] ?? null
        ]);
    }

    public function createTrainer()
    {
        $data = $this->getRequestData();
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['trainer_email']) || empty($data['trainer_password']) || empty($data['trainer_name'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password, trainer_email, trainer_password and trainer_name required']);
            return;
        }

        $admin = $this->admin->findByEmail($data['admin_email']);
        if (!$admin || !password_verify($data['admin_password'], $admin['password']) || $admin['role'] !== 'super_admin') {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $profileData = $this->normalizeTrainerProfile($data);
        if (empty($profileData['profile_description']) || !isset($profileData['experience_years']) || empty($profileData['expertise']) || empty($profileData['phone']) || empty($profileData['location']) || empty($profileData['photo_url'])) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'profile_description, experience_years, expertise, phone, location and photo are required']);
            return;
        }

        $name = trim((string) $data['trainer_name']);
        $ok = $this->admin->create($data['trainer_email'], $data['trainer_password'], 'trainer', $name, $profileData);
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
            'profileDescription' => $trainer['profile_description'] ?? null,
            'experienceYears' => $trainer['experience_years'] ?? null,
            'photoUrl' => $trainer['photo_url'] ?? null,
            'expertise' => $trainer['expertise'] ?? null,
            'phone' => $trainer['phone'] ?? null,
            'location' => $trainer['location'] ?? null,
            'linkedinUrl' => $trainer['linkedin_url'] ?? null,
            'websiteUrl' => $trainer['website_url'] ?? null,
            'lastLogin' => $trainer['last_logged_in'] ?? null
        ]);
    }

    public function listTrainers()
    {
        $data = $this->getRequestData();
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
        $data = $this->getRequestData();
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
        if (isset($data['trainer_name'])) {
            $fields['name'] = trim((string) $data['trainer_name']);
        }
        if (isset($data['trainer_password'])) {
            $fields['password'] = $data['trainer_password'];
        }

        $profileData = $this->normalizeTrainerProfile($data);
        foreach ($profileData as $key => $value) {
            $fields[$key] = $value;
        }

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
            'profileDescription' => $trainer['profile_description'] ?? null,
            'experienceYears' => $trainer['experience_years'] ?? null,
            'photoUrl' => $trainer['photo_url'] ?? null,
            'expertise' => $trainer['expertise'] ?? null,
            'phone' => $trainer['phone'] ?? null,
            'location' => $trainer['location'] ?? null,
            'linkedinUrl' => $trainer['linkedin_url'] ?? null,
            'websiteUrl' => $trainer['website_url'] ?? null,
            'lastLogin' => $trainer['last_logged_in'] ?? null
        ]);
    }

    public function deleteTrainer()
    {
        $data = $this->getRequestData();
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
