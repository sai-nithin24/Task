<?php
declare(strict_types=1);

/**
 * ProjectModel — Firestore-backed project operations.
 *
 * Collection: projects/{id}
 * Fields: user_id, name, description, color, is_archived, task_count, done_count, created_at, updated_at
 */
class ProjectModel
{
    private FirestoreClient $db;
    private const COLLECTION = 'projects';

    public function __construct()
    {
        $this->db = FirestoreClient::getInstance();
    }

    /** @return array<int, array> */
    public function allForUser(string $userId): array
    {
        $projects = $this->db->query(self::COLLECTION, [
            ['user_id',     '==', $userId],
            ['is_archived', '==', false],
        ], [['created_at', 'DESCENDING']]);

        // Enrich each project with live task counts from the tasks collection
        foreach ($projects as &$project) {
            $counts = $this->getTaskCounts($project['id']);
            $project['task_count'] = $counts['total'];
            $project['done_count'] = $counts['done'];
        }
        unset($project);

        return $projects;
    }

    public function findById(string $id, string $userId): array|false
    {
        $doc = $this->db->getDocument(self::COLLECTION, $id);
        if (!$doc || $doc['user_id'] !== $userId) {
            return false;
        }
        return $doc;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, string $userId): string
    {
        $docData = [
            'user_id'     => $userId,
            'name'        => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'color'       => $data['color'] ?? '#6366f1',
            'is_archived' => false,
            'task_count'  => 0,
            'done_count'  => 0,
            'created_at'  => date('c'),
            'updated_at'  => date('c'),
        ];

        return $this->db->addDocument(self::COLLECTION, $docData);
    }

    /** @param array<string,mixed> $data */
    public function update(string $id, array $data, string $userId): bool
    {
        $doc = $this->findById($id, $userId);
        if (!$doc) return false;

        $this->db->updateDocument(self::COLLECTION, $id, [
            'name'        => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'color'       => $data['color'] ?? '#6366f1',
            'updated_at'  => date('c'),
        ]);
        return true;
    }

    public function archive(string $id, string $userId): bool
    {
        $doc = $this->findById($id, $userId);
        if (!$doc) return false;

        $this->db->updateDocument(self::COLLECTION, $id, [
            'is_archived' => true,
            'updated_at'  => date('c'),
        ]);
        return true;
    }

    public function delete(string $id, string $userId): bool
    {
        $doc = $this->findById($id, $userId);
        if (!$doc) return false;

        // Hard delete the project document
        $this->db->deleteDocument(self::COLLECTION, $id);

        // Cascade: delete all tasks belonging to this project
        $tasks = $this->db->query('tasks', [
            ['project_id', '==', $id],
        ]);
        foreach ($tasks as $task) {
            $this->db->deleteDocument('tasks', $task['id']);
        }

        return true;
    }

    /** Compute task counts for a project by querying the tasks collection. */
    public function getTaskCounts(string $projectId): array
    {
        $tasks = $this->db->query('tasks', [
            ['project_id', '==', $projectId],
            ['is_deleted',  '==', false],
        ]);

        $counts = ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];
        foreach ($tasks as $task) {
            $counts['total']++;
            $status = $task['status'] ?? 'todo';
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        return $counts;
    }
}
