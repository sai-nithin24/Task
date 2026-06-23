<?php
declare(strict_types=1);

class ProjectController extends BaseController
{
    private ProjectModel  $projects;
    private ActivityModel $activity;

    public function __construct()
    {
        $this->projects = new ProjectModel();
        $this->activity = new ActivityModel();
    }

    /** GET /projects */
    public function index(): void
    {
        $this->requireAuth();
        $userId   = (string)$this->authUser['user_id'];
        $projects = $this->projects->allForUser($userId);
        Response::success(['projects' => $projects]);
    }

    /** POST /projects */
    public function store(): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];
        $data   = $this->body();
        $errors = $this->validate($data, ['name']);

        if ($errors) {
            Response::error('Project name is required.', 422);
        }

        $id      = $this->projects->create($data, $userId);
        $project = $this->projects->findById($id, $userId);

        $this->activity->log($userId, 'project_created', null, $id, ['name' => $project['name']]);

        Response::success(['project' => $project], 'Project created.', 201);
    }

    /** GET /projects/:id */
    public function show(array $params): void
    {
        $this->requireAuth();
        $userId  = (string)$this->authUser['user_id'];
        $project = $this->projects->findById((string)$params['id'], $userId);

        if (!$project) {
            Response::notFound('Project not found.');
        }

        Response::success(['project' => $project]);
    }

    /** PUT /projects/:id */
    public function update(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];
        $data   = $this->body();
        $errors = $this->validate($data, ['name']);

        if ($errors) {
            Response::error('Project name is required.', 422);
        }

        if (!$this->projects->findById((string)$params['id'], $userId)) {
            Response::notFound('Project not found.');
        }

        $this->projects->update((string)$params['id'], $data, $userId);
        $this->activity->log($userId, 'project_updated', null, (string)$params['id']);

        $project = $this->projects->findById((string)$params['id'], $userId);
        Response::success(['project' => $project], 'Project updated.');
    }

    /** DELETE /projects/:id */
    public function destroy(array $params): void
    {
        $this->requireAuth();
        $userId = (string)$this->authUser['user_id'];

        if (!$this->projects->findById((string)$params['id'], $userId)) {
            Response::notFound('Project not found.');
        }

        $this->projects->delete((string)$params['id'], $userId);
        $this->activity->log($userId, 'project_deleted', null, (string)$params['id']);

        Response::success(null, 'Project deleted.');
    }
}
