<?php

class Purchase
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function addPurchaseByUserId($userId, $courseId, $paymentPlan = 'one_time', $paidInstallments = 1)
    {
        $sql = "INSERT INTO purchases(user_id, course_id, payment_plan, paid_installments, status) VALUES(:user_id, :course_id, :payment_plan, :paid_installments, :status)";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute([
                'user_id' => $userId,
                'course_id' => $courseId,
                'payment_plan' => $paymentPlan,
                'paid_installments' => (int) $paidInstallments,
                'status' => $paidInstallments > 0 ? 'active' : 'pending'
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    // update purchase for user and course
    public function updatePurchaseByUserId($userId, $courseId, $paymentPlan = 'one_time', $paidInstallments = 1, $status = 'active')    
    {
        $sql = "UPDATE purchases SET payment_plan = :payment_plan, paid_installments = :paid_installments, status = :status WHERE user_id = :user_id AND course_id = :course_id";
        $stmt = $this->db->prepare($sql);
        try {
            return $stmt->execute([
                'user_id' => $userId,
                'course_id' => $courseId,
                'payment_plan' => $paymentPlan,
                'paid_installments' => (int) $paidInstallments,
                'status' => $status
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function getPurchaseByUserAndCourse($userId, $courseId)
    {
        $sql = "SELECT * FROM purchases WHERE user_id = :user_id AND course_id = :course_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getPurchaseDetailsByUserAndCourse($userId, $courseId)
    {
        $sql = "SELECT p.*, u.email AS user_email, c.title AS course_title
                FROM purchases p
                JOIN users u ON u.id = p.user_id
                LEFT JOIN courses c ON c.id = p.course_id
                WHERE p.user_id = :user_id AND p.course_id = :course_id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'course_id' => $courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
