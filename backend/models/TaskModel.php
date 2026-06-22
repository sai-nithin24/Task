<?php
declare(strict_types=1);

class TaskModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Returns tasks for a project with optional search + filters.
     *
     * @return array<int, array>
     */
    public function allForProject(int $projectId, array $filters = []): array
    {
        $sql    = 'SELECT t.*, u.name AS assigned_name, u.avatar_color AS assigned_color
                   FROM tasks t
                   LEFT JOIN users u ON u.id = t.assigned_to
                   WHERE t.project_id = ? AND t.is_deleted = 0';
        $params = [$projectId];

        if (!empty($filters['status'])) {
            $sql     .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql     .= ' AND t.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['search'])) {
            $sql     .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
            $term     = '%' . $filters['search'] . '%';
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['due_from'])) {
            $sql     .= ' AND t.due_date >= ?';
            $params[] = $filters['due_from'];
        }
        if (!empty($filters['due_to'])) {
            $sql     .= ' AND t.due_date <= ?';
            $params[] = $filters['due_to'];
        }

        $sql .= ' ORDER BY t.position_index ASC, t.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.name AS assigned_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             WHERE t.id = ? AND t.is_deleted = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (project_id, assigned_to, title, description, status, priority, due_date, position_index)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int)$data['project_id'],
            !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            trim($data['title']),
            trim($data['description'] ?? ''),
            $data['status']   ?? 'todo',
            $data['priority'] ?? 'medium',
            !empty($data['due_date']) ? $data['due_date'] : null,
            (int)($data['position_index'] ?? 0),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tasks
             SET title = ?, description = ?, status = ?, priority = ?,
                 due_date = ?, assigned_to = ?, position_index = ?
             WHERE id = ? AND is_deleted = 0'
        );
        return $stmt->execute([
            trim($data['title']),
            trim($data['description'] ?? ''),
            $data['status']   ?? 'todo',
            $data['priority'] ?? 'medium',
            !empty($data['due_date']) ? $data['due_date'] : null,
            !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            (int)($data['position_index'] ?? 0),
            $id,
        ]);
    }

    /** Update only the status (for drag & drop column changes). */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['todo', 'in_progress', 'review', 'done'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE tasks SET status = ? WHERE id = ? AND is_deleted = 0');
        return $stmt->execute([$status, $id]);
    }

    /** Soft-delete a task. */
    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE tasks SET is_deleted = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Permanently removes a task. */
    public function hardDelete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Restore a soft-deleted task. */
    public function restore(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE tasks SET is_deleted = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Update position_index for drag & drop reorder. */
    public function reorder(int $id, int $position): bool
    {
        $stmt = $this->db->prepare('UPDATE tasks SET position_index = ? WHERE id = ?');
        return $stmt->execute([$position, $id]);
    }

    /** Stats for the dashboard. */
    public function statsForProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, COUNT(*) AS cnt
             FROM tasks
             WHERE project_id = ? AND is_deleted = 0
             GROUP BY status'
        );
        $stmt->execute([$projectId]);
        $rows  = $stmt->fetchAll();
        $stats = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int)$row['cnt'];
            $stats['total']       += (int)$row['cnt'];
        }
        return $stats;
    }
}
