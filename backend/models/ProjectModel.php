<?php
declare(strict_types=1);

class ProjectModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** @return array<int, array> */
    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, 
                    COUNT(t.id) AS task_count,
                    SUM(CASE WHEN t.status = "done" AND t.is_deleted = 0 THEN 1 ELSE 0 END) AS done_count
             FROM projects p
             LEFT JOIN tasks t ON t.project_id = p.id AND t.is_deleted = 0
             WHERE p.user_id = ? AND p.is_archived = 0
             GROUP BY p.id
             ORDER BY p.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $userId): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (user_id, name, description, color) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            trim($data['name']),
            trim($data['description'] ?? ''),
            $data['color'] ?? '#6366f1',
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE projects SET name = ?, description = ?, color = ? WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([
            trim($data['name']),
            trim($data['description'] ?? ''),
            $data['color'] ?? '#6366f1',
            $id,
            $userId,
        ]);
    }

    public function archive(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE projects SET is_archived = 1 WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM projects WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }
}
