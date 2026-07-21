<?php

class Purchase
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function addPurchaseByUserId($userId, $courseId)
    {
        $sql = "INSERT INTO purchases(user_id, course_id) VALUES(:user_id, :course_id)";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function removePurchaseByUserId($userId, $courseId)
    {
        $sql = "DELETE FROM purchases WHERE user_id = :user_id AND course_id = :course_id";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getCourseIdsByUserId($userId)
    {
        $sql = "SELECT course_id FROM purchases WHERE user_id = :user_id ORDER BY purchased_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return array_map(function($r){ return $r['course_id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getUsersByCourseId($courseId)
    {
        $sql = "SELECT u.id, u.name, u.email FROM purchases p JOIN users u ON u.id = p.user_id WHERE p.course_id = :course_id ORDER BY u.name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['course_id' => $courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
