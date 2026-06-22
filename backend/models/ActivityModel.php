<?php
declare(strict_types=1);

class ActivityModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * @param array<string,mixed>|null $meta
     */
    public function log(int $userId, string $action, ?int $taskId = null, ?int $projectId = null, ?array $meta = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_logs (user_id, task_id, project_id, action, meta) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $taskId,
            $projectId,
            $action,
            $meta !== null ? json_encode($meta) : null,
        ]);
    }

    /** @return array<int, array> */
    public function recentForProject(int $projectId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.name AS user_name, u.avatar_color, t.title AS task_title
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN tasks t ON t.id = a.task_id
             WHERE a.project_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$projectId, $limit]);
        return $stmt->fetchAll();
    }

    /** @return array<int, array> */
    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, t.title AS task_title
             FROM activity_logs a
             LEFT JOIN tasks t ON t.id = a.task_id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
