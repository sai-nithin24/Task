<?php
declare(strict_types=1);

/**
 * ActivityModel — Firestore-backed activity log.
 *
 * Collection: activity_logs/{id}
 * Fields: user_id, user_name, avatar_color, task_id, task_title,
 *         project_id, action, meta, created_at
 *
 * All join-time fields (user_name, avatar_color, task_title) are
 * denormalized at write time so reads need no joins.
 */
class ActivityModel
{
    private FirestoreClient $db;
    private const COLLECTION = 'activity_logs';

    public function __construct()
    {
        $this->db = FirestoreClient::getInstance();
    }

    /**
     * Log an activity event.
     * Fetches user and task names for denormalization so reads are fast.
     *
     * @param array<string,mixed>|null $meta
     */
    public function log(
        string  $userId,
        string  $action,
        ?string $taskId    = null,
        ?string $projectId = null,
        ?array  $meta      = null
    ): void {
        // Denormalize user info
        $userName    = null;
        $avatarColor = null;
        $userDoc     = $this->db->getDocument('users', $userId);
        if ($userDoc) {
            $userName    = $userDoc['name']         ?? null;
            $avatarColor = $userDoc['avatar_color'] ?? null;
        }

        // Denormalize task title
        $taskTitle = null;
        if ($taskId) {
            $taskDoc   = $this->db->getDocument('tasks', $taskId);
            $taskTitle = $taskDoc['title'] ?? null;
        }

        $docData = [
            'user_id'      => $userId,
            'user_name'    => $userName,
            'avatar_color' => $avatarColor,
            'task_id'      => $taskId,
            'task_title'   => $taskTitle,
            'project_id'   => $projectId,
            'action'       => $action,
            'meta'         => $meta ? json_encode($meta) : null,
            'created_at'   => date('c'),
        ];

        $this->db->addDocument(self::COLLECTION, $docData);
    }

    /** @return array<int, array> */
    public function recentForProject(string $projectId, int $limit = 30): array
    {
        return $this->db->query(self::COLLECTION, [
            ['project_id', '==', $projectId],
        ], [['created_at', 'DESCENDING']], $limit);
    }

    /** @return array<int, array> */
    public function recentForUser(string $userId, int $limit = 20): array
    {
        return $this->db->query(self::COLLECTION, [
            ['user_id', '==', $userId],
        ], [['created_at', 'DESCENDING']], $limit);
    }
}
