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
        //Updated the TRUE issue
        //Ganesh L G
        $sql = "SELECT id, title, description, thumbnail_url, price, duration_hours, instructor_name, is_published, published_at FROM courses WHERE TRUE";
        // By default callers can append a WHERE clause externally; keep signature simple.
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($id)
    {
        $sql = "SELECT id, title, description, thumbnail_url, price, duration_hours, instructor_name, is_published, published_at FROM courses WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findModulesByCourseId($courseId)
    {
        $sql = "SELECT id, course_id, title, description, video_url, type, live_link, recorded_video_url FROM modules WHERE course_id = :course_id ORDER BY id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Update to get the lessons with resource
    public function findLessonsByModuleId($moduleId)
    {
        $sql = "SELECT l.id, l.module_id, l.title, l.lesson_type, l.content, l.`order`, 
                       lr.id AS resource_id, lr.title AS resource_title, lr.url AS resource_url, lr.file_type AS resource_file_type
                FROM lessons l
                LEFT JOIN lesson_resources lr ON l.id = lr.lesson_id
                WHERE l.module_id = :module_id
                ORDER BY l.`order`, l.id, lr.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['module_id' => $moduleId]);
        $lessons = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lessonId = $row['id'];
            if (!isset($lessons[$lessonId])) {
                $lessons[$lessonId] = [
                    'id' => $row['id'],
                    'module_id' => $row['module_id'],
                    'title' => $row['title'],
                    'lesson_type' => $row['lesson_type'],
                    'content' => $row['content'],
                    'order' => $row['order'],
                    'resources' => []
                ];
            }
            if ($row['resource_id']) {
                $lessons[$lessonId]['resources'][] = [
                    'id' => $row['resource_id'],
                    'title' => $row['resource_title'],
                    'url' => $row['resource_url'],
                    'file_type' => $row['resource_file_type']
                ];
            }
        }
        return array_values($lessons);
    }

    // public function findLessonsByModuleId($moduleId)
    // {
    //     $sql = "SELECT id, module_id, title, lesson_type, content, `order` FROM lessons WHERE module_id = :module_id ORDER BY `order`, id";
    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute(['module_id' => $moduleId]);
    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

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
        $sql = "INSERT INTO courses(id, title, description, thumbnail_url, price, duration_hours, instructor_name, is_published, published_at)
                VALUES(:id, :title, :description, :thumbnail_url, :price, :duration_hours, :instructor_name, :is_published, :published_at)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'price' => $data['price'] ?? null,
            'duration_hours' => $data['duration_hours'] ?? null,
            'instructor_name' => $data['instructor_name'] ?? null,
            'is_published' => $data['is_published'] ?? 0,
            'published_at' => $data['published_at'] ?? null,
        ]);
    }

    public function updateCourse($data)
    {
        $fields = [];
        $params = ['id' => $data['id']];
        $allowed = ['title','description','thumbnail_url','price','duration_hours','instructor_name','is_published','published_at'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteCourse($id)
    {
        try {
            $this->db->beginTransaction();
            // delete lessons of modules belonging to course
            $sql = "DELETE FROM lessons WHERE module_id IN (SELECT id FROM modules WHERE course_id = :id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            // delete modules
            $sql = "DELETE FROM modules WHERE course_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            // delete course
            $sql = "DELETE FROM courses WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function publishCourse($id)
    {
        $sql = "UPDATE courses SET is_published = TRUE, published_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function unpublishCourse($id)
    {
        $sql = "UPDATE courses SET is_published = 0, published_at = NULL WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function createModule($data)
    {
        $sql = "INSERT INTO modules(id, course_id, title, description, video_url, type, live_link, recorded_video_url) 
                VALUES(:id, :course_id, :title, :description, :video_url, :type, :live_link, :recorded_video_url)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'type' => $data['type'] ?? null,
            'live_link' => $data['live_link'] ?? null,
            'recorded_video_url' => $data['recorded_video_url'] ?? null,
        ]);
    }

    public function updateModule($data)
    {
        $fields = [];
        $params = ['id' => $data['id']];
        $allowed = ['course_id', 'title', 'description', 'video_url', 'type', 'live_link', 'recorded_video_url'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = "UPDATE modules SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteModule($id)
    {
        $sql = "DELETE FROM modules WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function createLesson($data)
    {
        $sql = "INSERT INTO lessons(id, module_id, title, lesson_type, content, order) 
                VALUES(:id, :module_id, :title, :lesson_type, :content, :order)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'module_id' => $data['module_id'],
            'title' => $data['title'],
            'lesson_type' => $data['lesson_type'] ?? null,
            'content' => $data['content'] ?? null,
            'order' => $data['order'] ?? 0,
        ]);
    }

    public function updateLesson($data)
    {
        $fields = [];
        $params = ['id' => $data['id']];
        $allowed = ['module_id', 'title', 'lesson_type', 'content', 'order'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "`$col` = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = "UPDATE lessons SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteLesson($id)
    {
        $sql = "DELETE FROM lessons WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function findResourcesByLessonId($lessonId)
    {
        $sql = "SELECT id, lesson_id, title, url, file_type FROM lesson_resources WHERE lesson_id = :lesson_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['lesson_id' => $lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resourceExists($id)
    {
        $sql = "SELECT id FROM lesson_resources WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function createResource($data)
    {
        $sql = "INSERT INTO lesson_resources(id, lesson_id, title, url, file_type) 
                VALUES(:id, :lesson_id, :title, :url, :file_type)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $data['id'],
            'lesson_id' => $data['lesson_id'],
            'title' => $data['title'],
            'url' => $data['url'],
            'file_type' => $data['file_type'] ?? null,
        ]);
    }

    public function updateResource($data)
    {
        $fields = [];
        $params = ['id' => $data['id']];
        $allowed = ['title', 'url', 'file_type'];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "$col = :$col";
                $params[$col] = $data[$col];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = "UPDATE lesson_resources SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteResource($id)
    {
        $sql = "DELETE FROM lesson_resources WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}

