<?php

class Dashboard
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getTotals(): array
    {
        $counts = [];

        $counts['total_courses'] = (int) $this->db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $counts['total_modules'] = (int) $this->db->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        $counts['total_lessons'] = (int) $this->db->query("SELECT COUNT(*) FROM lessons")->fetchColumn();
        $counts['total_students'] = (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();

        return $counts;
    }

    public function getEnrollmentTrend(int $months = 6): array
    {
        if ($months < 1) {
            $months = 6;
        }

        $startDate = (new DateTime('first day of this month'))->modify('-' . ($months - 1) . ' months')->format('Y-m-01');

        // $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
        //         FROM users
        //         WHERE created_at >= :start_date
        //         GROUP BY month
        //         ORDER BY month";

        // $stmt = $this->db->prepare($sql);
        // $stmt->execute(['start_date' => $startDate]);
        // $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "SELECT DATE_TRUNC('month', created_at) AS month,
               COUNT(*) AS count
        FROM users
        WHERE created_at >= :start_date
        GROUP BY DATE_TRUNC('month', created_at)
        ORDER BY month";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start_date' => $startDate]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // // Format the month if needed
        // foreach ($rows as &$row) {
        //     $row['month'] = date('Y-m', strtotime($row['month']));
        // }

        $countsByMonth = [];
        foreach ($rows as $row) {
            $countsByMonth[$row['month']] = (int) $row['count'];
        }

        $trend = [];
        $current = new DateTime('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthKey = $current->format('Y-m');
            $trend[] = [
                'month' => $monthKey,
                'count' => $countsByMonth[$monthKey] ?? 0,
            ];
            $current->modify('-1 month');
        }

        return array_reverse($trend);
    }

    public function getRecentActivities(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, "user", activity, created_at
            FROM activity_logs
            ORDER BY created_at DESC
            LIMIT :limit'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addActivity(string $user, string $activity): bool
{
    $sql = 'INSERT INTO activity_logs ("user", activity)
            VALUES (:user, :activity)';

    $stmt = $this->db->prepare($sql);

    return $stmt->execute([
        ':user' => $user,
        ':activity' => $activity,
    ]);
}
}
