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

        // Create resources if provided
        if (!empty($data['resources']) && is_array($data['resources'])) {
            foreach ($data['resources'] as $resource) {
                $resource['lesson_id'] = $data['id'];
                $this->course->createResource($resource);
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Lesson created']);
    }

    public function uploadFile()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Method not allowed']);
            return;
        }

        $adminEmail = $_POST['admin_email'] ?? null;
        $adminPassword = $_POST['admin_password'] ?? null;
        if (empty($adminEmail) || empty($adminPassword) || empty($_FILES['file'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and file are required']);
            return;
        }

        $admin = $this->authorizeAdmin($adminEmail, $adminPassword);
        if (!$admin || !in_array($admin['role'], ['super_admin', 'trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $upload = $_FILES['file'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'File upload error', 'error' => $upload['error']]);
            return;
        }

        $originalName = basename($upload['name']);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        if ($safeName === '') {
            $safeName = 'file';
        }

        $folder = $_POST['folder'] ?? 'uploads';
        $safeFolder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folder);
        if ($safeFolder === '') {
            $safeFolder = 'uploads';
        }

        $uploadDir = __DIR__ . '/../storage/uploads/' . $safeFolder;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . '/' . $safeName;
        if (file_exists($destination)) {
            $destination = $uploadDir . '/' . pathinfo($safeName, PATHINFO_FILENAME) . '_' . time() . '.' . pathinfo($safeName, PATHINFO_EXTENSION);
        }

        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not move uploaded file']);
            return;
        }

        $relativePath = 'uploads/' . $safeFolder . '/' . basename($destination);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'File uploaded',
            'file_name' => basename($destination),
            'folder' => $safeFolder,
            'relative_path' => $relativePath,
            'url' => $relativePath,
            'file_url' => $relativePath
        ]);
    }

    public function getModulesByCourseId()
    {
        $courseId = $_GET['course_id'] ?? null;
        if (!$courseId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'course_id query parameter required']);
            return;
        }

        $modules = $this->course->findModulesByCourseId($courseId);
        header('Content-Type: application/json');
        echo json_encode(['modules' => $modules]);
    }

    public function getLessonsByModuleId()
    {
        $moduleId = $_GET['module_id'] ?? null;
        if (!$moduleId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'module_id query parameter required']);
            return;
        }

        $lessons = $this->course->findLessonsByModuleId($moduleId);
        
        // Fetch resources for each lesson
        foreach ($lessons as &$lesson) {
            $lesson['resources'] = $this->course->findResourcesByLessonId($lesson['id']);
        }
        
        header('Content-Type: application/json');
        echo json_encode(['lessons' => $lessons]);
    }

    public function getResourcesByLessonId()
    {
        $lessonId = $_GET['lesson_id'] ?? null;
        if (!$lessonId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'lesson_id query parameter required']);
            return;
        }

        $resources = $this->course->findResourcesByLessonId($lessonId);
        header('Content-Type: application/json');
        echo json_encode(['resources' => $resources]);
    }

    public function serveUpload($extension, $filename)
    {
        $extension = preg_replace('/[^a-z0-9_-]/', '', strtolower($extension));
        $filename = basename(urldecode($filename));
        if ($filename === '' || $extension === '') {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Invalid file path']);
            return;
        }

        $filePath = __DIR__ . '/../storage/uploads/' . $extension . '/' . $filename;
        if (!file_exists($filePath) || !is_file($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'File not found']);
            return;
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: public, max-age=604800');
        readfile($filePath);
    }

    public function updateModule()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->updateModule($data);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not update module']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Module updated']);
    }

    public function deleteModule()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->deleteModule($data['id']);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not delete module']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Module deleted']);
    }

    // public function updateLesson()
    // {
    //     $data = json_decode(file_get_contents('php://input'), true);
    //     if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
    //         http_response_code(400);
    //         header('Content-Type: application/json');
    //         echo json_encode(['message' => 'admin_email, admin_password and id required']);
    //         return;
    //     }

    //     $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
    //     if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
    //         http_response_code(403);
    //         header('Content-Type: application/json');
    //         echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
    //         return;
    //     }

    //     $ok = $this->course->updateLesson($data);
    //     if (!$ok) {
    //         http_response_code(500);
    //         header('Content-Type: application/json');
    //         echo json_encode(['message' => 'Could not update lesson']);
    //         return;
    //     }

    //     // Handle resources if provided
    //     if (!empty($data['resources']) && is_array($data['resources'])) {
    //         // If replace_resources flag is set, delete existing resources first
    //         if (!empty($data['replace_resources'])) {
    //             $existingResources = $this->course->findResourcesByLessonId($data['id']);
    //             foreach ($existingResources as $resource) {
    //                 $this->course->deleteResource($resource['id']);
    //             }
    //         }
    //         // Create/add resources
    //         foreach ($data['resources'] as $resource) {
    //             if (empty($resource['id'])) {
    //                 // Generate ID if not provided
    //                 $resource['id'] = 'resource_' . uniqid();
    //             }
    //             $resource['lesson_id'] = $data['id'];
    //             if (array_key_exists('id', $resource) && $this->course->resourceExists($resource['id'])) {
    //                 // Update existing resource
    //                 $this->course->updateResource($resource);
    //             } else {
    //                 // Create new resource
    //                 $this->course->createResource($resource);
    //             }
    //         }
    //     }

    //     header('Content-Type: application/json');
    //     echo json_encode(['message' => 'Lesson updated']);
    // }

    public function updateLesson()
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'admin_email, admin_password and id required'
        ]);
        return;
    }

    $admin = $this->authorizeAdmin(
        $data['admin_email'],
        $data['admin_password']
    );

    if (!$admin || !in_array($admin['role'], ['super_admin', 'trainer'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Forbidden: trainer or super_admin required'
        ]);
        return;
    }

    // Update lesson details
    $ok = $this->course->updateLesson($data);

    if (!$ok) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Could not update lesson'
        ]);
        return;
    }

    // Handle resources if provided
    if (isset($data['resources']) && is_array($data['resources'])) {

        // Get existing resources for this lesson
        $existingResources = $this->course->findResourcesByLessonId($data['id']);

        // If replace_resources flag is set, remove all existing resources first
        if (!empty($data['replace_resources'])) {

            foreach ($existingResources as $resource) {
                $this->course->deleteResource($resource['id']);
            }

        } else {

            // Collect incoming resource IDs
            $incomingIds = [];

            foreach ($data['resources'] as $resource) {
                if (!empty($resource['id'])) {
                    $incomingIds[] = $resource['id'];
                }
            }

            // Delete resources that were removed from frontend
            foreach ($existingResources as $existingResource) {
                if (!in_array($existingResource['id'], $incomingIds)) {
                    $this->course->deleteResource($existingResource['id']);
                }
            }
        }

        // Create or update submitted resources
        foreach ($data['resources'] as $resource) {

            $isNew = empty($resource['id']);

            if ($isNew) {
                $resource['id'] = 'resource_' . uniqid();
            }

            $resource['lesson_id'] = $data['id'];

            if (!$isNew && $this->course->resourceExists($resource['id'])) {
                $this->course->updateResource($resource);
            } else {
                $this->course->createResource($resource);
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Lesson updated'
    ]);
}

    public function deleteLesson()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->deleteLesson($data['id']);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not delete lesson']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Lesson deleted']);
    }

    public function listCourses()
    {
        // return only published courses to public/student users
        $courses = $this->course->getAll();
        $published = array_filter($courses, function($c) {
            return isset($c['is_published']) && $c['is_published'];
        });
        header('Content-Type: application/json');
        echo json_encode(['courses' => array_values($published)]);
    }

    // ADMIN: list all courses (including unpublished)
    public function adminListCourses()
    {
        $email = $_GET['admin_email'] ?? null;
        $password = $_GET['admin_password'] ?? null;
        if (!$email || !$password) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email and admin_password required']);
            return;
        }

        $admin = $this->authorizeAdmin($email, $password);
        if (!$admin) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden']);
            return;
        }

        $courses = $this->course->getAll();
        header('Content-Type: application/json');
        echo json_encode(['courses' => $courses]);
    }

    public function updateCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || !in_array($admin['role'], ['super_admin','trainer'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: trainer or super_admin required']);
            return;
        }

        $ok = $this->course->updateCourse($data);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not update course']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Course updated']);
    }

    public function deleteCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || $admin['role'] !== 'super_admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $ok = $this->course->deleteCourse($data['id']);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not delete course']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Course deleted']);
    }

    public function publishCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || $admin['role'] !== 'super_admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $ok = $this->course->publishCourse($data['id']);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not publish course']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Course published']);
    }

    public function unpublishCourse()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['admin_email']) || empty($data['admin_password']) || empty($data['id'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'admin_email, admin_password and id required']);
            return;
        }

        $admin = $this->authorizeAdmin($data['admin_email'], $data['admin_password']);
        if (!$admin || $admin['role'] !== 'super_admin') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Forbidden: super_admin required']);
            return;
        }

        $ok = $this->course->unpublishCourse($data['id']);
        if (!$ok) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Could not unpublish course']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['message' => 'Course unpublished']);
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
