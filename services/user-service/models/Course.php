<?php

class Course
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll()
    {
        $sql = "SELECT id, title, description, thumbnail_url, price, duration_hours, instructor_name FROM courses";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $sql = "SELECT id, title, description, thumbnail_url, price, duration_hours, instructor_name FROM courses WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findModulesByCourseId($courseId)
    {
        $sql = "SELECT id, course_id, title, video_url FROM modules WHERE course_id = :course_id ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLessonsByModuleId($moduleId)
    {
        $sql = "SELECT id, module_id, title, lesson_type FROM lessons WHERE module_id = :module_id ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['module_id' => $moduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCourseWithContent($id)
    {
        $course = $this->findById($id);
        if (!$course) {
            return null;
        }

        $modules = $this->findModulesByCourseId($id);
        foreach ($modules as &$module) {
            $module['lessons'] = $this->findLessonsByModuleId($module['id']);
        }

        $course['modules'] = $modules;
        return $course;
    }

    public function createCourse($data)
    {
        $sql = "INSERT INTO courses(id, title, description, thumbnail_url, price, duration_hours, instructor_name)
                VALUES(:id, :title, :description, :thumbnail_url, :price, :duration_hours, :instructor_name)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'price' => $data['price'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'instructor_name' => $data['instructor_name'] ?? null,
        ]);
    }

    public function createModule($data)
    {
        $sql = "INSERT INTO modules(id, course_id, title, video_url) VALUES(:id, :course_id, :title, :video_url)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'video_url' => $data['video_url'] ?? null,
        ]);
    }

    public function createLesson($data)
    {
        $sql = "INSERT INTO lessons(id, module_id, title, lesson_type) VALUES(:id, :module_id, :title, :lesson_type)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'module_id' => $data['module_id'],
            'title' => $data['title'],
            'lesson_type' => $data['lesson_type'] ?? null,
        ]);
    }
}
