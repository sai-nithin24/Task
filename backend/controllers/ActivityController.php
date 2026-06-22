<?php
declare(strict_types=1);

class ActivityController extends BaseController
{
    private ActivityModel $activity;

    public function __construct()
    {
        $this->activity = new ActivityModel();
    }

    /** GET /projects/:project_id/activity */
    public function forProject(array $params): void
    {
        $this->requireAuth();
        $logs = $this->activity->recentForProject((int)$params['project_id']);
        Response::success(['logs' => $logs]);
    }

    /** GET /activity/me */
    public function forMe(): void
    {
        $this->requireAuth();
        $userId = (int)$this->authUser['user_id'];
        $logs   = $this->activity->recentForUser($userId);
        Response::success(['logs' => $logs]);
    }
}
