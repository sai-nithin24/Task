<?php
declare(strict_types=1);

class TaskController extends BaseController
{
    private TaskModel     $tasks;
    private ProjectModel  $projects;
    private ActivityModel $activity;

    public function __construct()
    {
        $this->tasks    = new TaskModel();
        $this->projects = new ProjectModel();
        $this->activity = new ActivityModel();
    }

    /** GET /projects/:project_id/tasks */
    public function index(array $params): void
    {
        $this->requireAuth();
        $userId    = (string)$this->authUser['user_id'];
        $projectId = (string)$params['project_id'];

        if (!$this->projects->findById($projectId, $userId)) {
            Response::notFound('Project not found.');
        }

        $filters = array_filter([
            'status'   => $this->query('status'),
            'priority' => $this->query('priority'),
            'search'   => $this->query('search'),
            'due_from' => $this->query('due_from'),
            'due_to'   => $this->query('due_to'),
        ]);

        $tasks = $this->tasks->allForProject($projectId, $filters);
        $stats = $this->tasks->statsForProject($projectId);

        Response::success(['tasks' => $tasks, 'stats' => $stats]);
    }

    /** POST /projects/:project_id/tasks */
    public function store(array $params): void
    {
        $this->requireAuth();
        $userId    = (string)$this->authUser['user_id'];
        $projectId = (string)$params['project_id'];

        if (!$this->projects->findById($projectId, $userId)) {
            Response::notFound('Project not found.');
        }

        $data   = $this->body();
        $errors = $this->validate($data, ['title']);

        if ($errors) {
            Response::error('Task title is required.', 422);
        }

        $validStatus   = ['todo', 'in_progress', 'review', 'done'];
        $validPriority = ['low', 'medium', 'high', 'urgent'];

        if (isset($data['status']) && !in_array($data['status'], $validStatus, true)) {
            Response::error('Invalid status value.', 422);
        }
        if (isset($data['priority']) && !in_array($data['priority'], $validPriority, true)) {
            Response::error('Invalid priority value.', 422);
        }

        $data['project_id'] = $projectId;
        $id   = $this->tasks->create($data);
        $task = $this->tasks->findById($id);

        $this->activity->log($userId, 'task_created', $id, $projectId, ['title' => $task['title']]);

        Response::success(['task' => $task], 'Task created.', 201);
    }

    /** GET /tasks/:id */
    public function show(array $params): void
    {
        $this->requireAuth();
        $task = $this->tasks->findById((string)$params['id']);

        if (!$task) {
            Response::notFound('Task not found.');
        }

        Response::success(['task' => $task]);
    }

    /** PUT /tasks/:id */
    public function update(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];
        $data   = $this->body();
        $errors = $this->validate($data, ['title']);

        if ($errors) {
            Response::error('Task title is required.', 422);
        }

        $task = $this->tasks->findById((string)$params['id']);
        if (!$task) {
            Response::notFound('Task not found.');
        }

        $this->tasks->update((string)$params['id'], $data);
        $this->activity->log($userId, 'task_updated', (string)$params['id'], (string)$task['project_id'], ['title' => $data['title']]);

        $updated = $this->tasks->findById((string)$params['id']);
        Response::success(['task' => $updated], 'Task updated.');
    }

    /** PATCH /tasks/:id/status */
    public function updateStatus(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];
        $data   = $this->body();

        if (empty($data['status'])) {
            Response::error('Status is required.', 422);
        }

        $task = $this->tasks->findById((string)$params['id']);
        if (!$task) {
            Response::notFound('Task not found.');
        }

        if (!$this->tasks->updateStatus((string)$params['id'], $data['status'])) {
            Response::error('Invalid status value.', 422);
        }

        $this->activity->log(
            $userId,
            'task_status_changed',
            (string)$params['id'],
            (string)$task['project_id'],
            ['from' => $task['status'], 'to' => $data['status']]
        );

        Response::success(null, 'Status updated.');
    }

    /** PATCH /tasks/:id/reorder */
    public function reorder(array $params): void
    {
        $this->requireAuth();
        $data = $this->body();

        if (!isset($data['position_index'])) {
            Response::error('position_index is required.', 422);
        }

        $task = $this->tasks->findById((string)$params['id']);
        if (!$task) {
            Response::notFound('Task not found.');
        }

        $this->tasks->reorder((string)$params['id'], (int)$data['position_index']);
        Response::success(null, 'Task reordered.');
    }

    /** DELETE /tasks/:id  (soft delete) */
    public function destroy(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];
        $task   = $this->tasks->findById((string)$params['id']);

        if (!$task) {
            Response::notFound('Task not found.');
        }

        $this->tasks->softDelete((string)$params['id']);
        $this->activity->log($userId, 'task_deleted', (string)$params['id'], (string)$task['project_id'], ['title' => $task['title']]);

        Response::success(null, 'Task deleted.');
    }

    /** PATCH /tasks/:id/restore */
    public function restore(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];

        // findDeletedById checks is_deleted == true
        $task = $this->tasks->findDeletedById((string)$params['id']);

        if (!$task) {
            Response::notFound('Deleted task not found.');
        }

        $this->tasks->restore((string)$params['id']);
        $this->activity->log($userId, 'task_restored', (string)$params['id'], (string)$task['project_id']);

        Response::success(null, 'Task restored.');
    }
}
