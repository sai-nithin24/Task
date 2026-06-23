<?php
declare(strict_types=1);

/**
 * TaskModel — Firestore-backed task operations.
 *
 * Collection: tasks/{id}
 * Fields: project_id, assigned_to, assigned_name, assigned_color,
 *         title, description, status, priority, due_date,
 *         position_index, is_deleted, created_at, updated_at
 */
class TaskModel
{
    private FirestoreClient $db;
    private const COLLECTION = 'tasks';
    private const VALID_STATUS   = ['todo', 'in_progress', 'review', 'done'];
    private const VALID_PRIORITY = ['low', 'medium', 'high', 'urgent'];

    public function __construct()
    {
        $this->db = FirestoreClient::getInstance();
    }

    /**
     * Returns tasks for a project with optional filters.
     * Firestore handles status/priority/date filters via queries.
     * Search (title/description LIKE) is done client-side on the result set.
     *
     * @return array<int, array>
     */
    public function allForProject(string $projectId, array $filters = []): array
    {
        $queryFilters = [
            ['project_id', '==', $projectId],
            ['is_deleted',  '==', false],
        ];

        // Single-field filters are pushed to Firestore
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUS, true)) {
            $queryFilters[] = ['status', '==', $filters['status']];
        }
        if (!empty($filters['priority']) && in_array($filters['priority'], self::VALID_PRIORITY, true)) {
            $queryFilters[] = ['priority', '==', $filters['priority']];
        }

        // Note: combining inequality filters (due_date range) on different fields
        // than equality filters requires composite Firestore indexes.
        // We fetch and filter dates in PHP to avoid index complexity.
        $tasks = $this->db->query(self::COLLECTION, $queryFilters, [
            ['position_index', 'ASCENDING'],
            ['created_at',     'ASCENDING'],
        ]);

        // PHP-side: date range and search filtering
        if (!empty($filters['due_from'])) {
            $tasks = array_filter($tasks, fn($t) =>
                !empty($t['due_date']) && $t['due_date'] >= $filters['due_from']
            );
        }
        if (!empty($filters['due_to'])) {
            $tasks = array_filter($tasks, fn($t) =>
                !empty($t['due_date']) && $t['due_date'] <= $filters['due_to']
            );
        }
        if (!empty($filters['search'])) {
            $term  = strtolower($filters['search']);
            $tasks = array_filter($tasks, fn($t) =>
                str_contains(strtolower($t['title'] ?? ''), $term) ||
                str_contains(strtolower($t['description'] ?? ''), $term)
            );
        }

        return array_values($tasks);
    }

    public function findById(string $id): array|false
    {
        $doc = $this->db->getDocument(self::COLLECTION, $id);
        if (!$doc || !empty($doc['is_deleted'])) {
            return false;
        }
        return $doc;
    }

    /** Find a soft-deleted task (for restore). */
    public function findDeletedById(string $id): array|false
    {
        $doc = $this->db->getDocument(self::COLLECTION, $id);
        if (!$doc || empty($doc['is_deleted'])) {
            return false;
        }
        return $doc;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): string
    {
        $docData = [
            'project_id'     => (string)($data['project_id'] ?? ''),
            'assigned_to'    => !empty($data['assigned_to']) ? (string)$data['assigned_to'] : null,
            'assigned_name'  => null,
            'assigned_color' => null,
            'title'          => trim($data['title']),
            'description'    => trim($data['description'] ?? ''),
            'status'         => in_array($data['status'] ?? '', self::VALID_STATUS, true)
                                    ? $data['status'] : 'todo',
            'priority'       => in_array($data['priority'] ?? '', self::VALID_PRIORITY, true)
                                    ? $data['priority'] : 'medium',
            'due_date'       => !empty($data['due_date']) ? $data['due_date'] : null,
            'position_index' => (int)($data['position_index'] ?? 0),
            'is_deleted'     => false,
            'created_at'     => date('c'),
            'updated_at'     => date('c'),
        ];

        return $this->db->addDocument(self::COLLECTION, $docData);
    }

    /** @param array<string,mixed> $data */
    public function update(string $id, array $data): bool
    {
        $this->db->updateDocument(self::COLLECTION, $id, [
            'title'          => trim($data['title']),
            'description'    => trim($data['description'] ?? ''),
            'status'         => in_array($data['status'] ?? '', self::VALID_STATUS, true)
                                    ? $data['status'] : 'todo',
            'priority'       => in_array($data['priority'] ?? '', self::VALID_PRIORITY, true)
                                    ? $data['priority'] : 'medium',
            'due_date'       => !empty($data['due_date']) ? $data['due_date'] : null,
            'assigned_to'    => !empty($data['assigned_to']) ? (string)$data['assigned_to'] : null,
            'position_index' => (int)($data['position_index'] ?? 0),
            'updated_at'     => date('c'),
        ]);
        return true;
    }

    public function updateStatus(string $id, string $status): bool
    {
        if (!in_array($status, self::VALID_STATUS, true)) {
            return false;
        }
        $this->db->updateDocument(self::COLLECTION, $id, [
            'status'     => $status,
            'updated_at' => date('c'),
        ]);
        return true;
    }

    public function softDelete(string $id): bool
    {
        $this->db->updateDocument(self::COLLECTION, $id, [
            'is_deleted' => true,
            'updated_at' => date('c'),
        ]);
        return true;
    }

    public function hardDelete(string $id): bool
    {
        $this->db->deleteDocument(self::COLLECTION, $id);
        return true;
    }

    public function restore(string $id): bool
    {
        $this->db->updateDocument(self::COLLECTION, $id, [
            'is_deleted' => false,
            'updated_at' => date('c'),
        ]);
        return true;
    }

    public function reorder(string $id, int $position): bool
    {
        $this->db->updateDocument(self::COLLECTION, $id, [
            'position_index' => $position,
            'updated_at'     => date('c'),
        ]);
        return true;
    }

    /** Stats for the dashboard — computed from the task list. */
    public function statsForProject(string $projectId): array
    {
        $tasks = $this->db->query(self::COLLECTION, [
            ['project_id', '==', $projectId],
            ['is_deleted',  '==', false],
        ]);

        $stats = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0, 'total' => 0];
        foreach ($tasks as $task) {
            $s = $task['status'] ?? 'todo';
            if (isset($stats[$s])) $stats[$s]++;
            $stats['total']++;
        }
        return $stats;
    }
}
