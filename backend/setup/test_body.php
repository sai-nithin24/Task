<?php
// Direct test of body() parsing — bypasses HTTP entirely
require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__, 2) . '/.env');
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Response.php';
require_once dirname(__DIR__) . '/core/JwtHelper.php';
require_once dirname(__DIR__) . '/core/AuthMiddleware.php';
require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/models/UserModel.php';
require_once dirname(__DIR__) . '/models/ProjectModel.php';
require_once dirname(__DIR__) . '/models/TaskModel.php';
require_once dirname(__DIR__) . '/models/ActivityModel.php';

$p = 0; $f = 0;
function check(string $label, bool $result, string $detail = ''): void {
    global $p, $f;
    if ($result) { echo "  PASS  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL; $p++; }
    else         { echo "  FAIL  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL; $f++; }
}

echo PHP_EOL . " TaskFlow Backend Logic Tests" . PHP_EOL;
echo str_repeat('-', 48) . PHP_EOL . PHP_EOL;

// ── 1. DB Connection ──────────────────────────────────────────
echo "[1] Database" . PHP_EOL;
try {
    $db = Database::getConnection();
    $ver = $db->query('SELECT VERSION()')->fetchColumn();
    check('PDO connection', true, $ver);
} catch (Exception $e) {
    check('PDO connection', false, $e->getMessage());
    exit(1);
}
echo PHP_EOL;

// ── 2. User operations ────────────────────────────────────────
echo "[2] UserModel" . PHP_EOL;
$email = 'body_test_' . time() . '@tf.test';
$users = new UserModel();

$uid = $users->create(['name' => 'Body Test', 'email' => $email, 'password' => 'Test@1234']);
check('Create user', $uid > 0, "id=$uid");

$u = $users->findByEmail($email);
check('findByEmail', (bool)$u);
check('Password hashed', str_starts_with($u['password'] ?? '', '$2y$'));
check('password_verify', password_verify('Test@1234', $u['password']));

$byId = $users->findById($uid);
check('findById (no password)', (bool)$byId && !isset($byId['password']));
echo PHP_EOL;

// ── 3. JWT ────────────────────────────────────────────────────
echo "[3] JWT" . PHP_EOL;
$token = JwtHelper::encode(['user_id' => $uid, 'email' => $email, 'role' => 'member']);
check('Token created', str_contains($token, '.'));
$payload = JwtHelper::decode($token);
check('Token decoded', $payload['user_id'] === $uid);
try { JwtHelper::decode('bad.token.here'); check('Bad token rejected', false); }
catch (InvalidArgumentException) { check('Bad token rejected', true); }
echo PHP_EOL;

// ── 4. ProjectModel ───────────────────────────────────────────
echo "[4] ProjectModel" . PHP_EOL;
$projects = new ProjectModel();
$projId = $projects->create(['name' => 'Test Project', 'description' => 'desc', 'color' => '#6366f1'], $uid);
check('Create project', $projId > 0, "id=$projId");

$proj = $projects->findById($projId, $uid);
check('findById', ($proj['name'] ?? '') === 'Test Project');

$all = $projects->allForUser($uid);
check('allForUser', count($all) >= 1);

$projects->update($projId, ['name' => 'Updated', 'description' => '', 'color' => '#10b981'], $uid);
$updated = $projects->findById($projId, $uid);
check('Update project', ($updated['name'] ?? '') === 'Updated');
echo PHP_EOL;

// ── 5. TaskModel ──────────────────────────────────────────────
echo "[5] TaskModel" . PHP_EOL;
$tasks = new TaskModel();
$taskId = $tasks->create([
    'project_id'     => $projId,
    'title'          => 'Test Task',
    'description'    => 'desc',
    'status'         => 'todo',
    'priority'       => 'high',
    'due_date'       => date('Y-m-d', strtotime('+7 days')),
    'position_index' => 0,
]);
check('Create task', $taskId > 0, "id=$taskId");

$task = $tasks->findById($taskId);
check('findById', ($task['title'] ?? '') === 'Test Task');
check('Status is todo', ($task['status'] ?? '') === 'todo');
check('Priority is high', ($task['priority'] ?? '') === 'high');

$tasks->updateStatus($taskId, 'in_progress');
$t = $tasks->findById($taskId);
check('updateStatus', ($t['status'] ?? '') === 'in_progress');

$tasks->update($taskId, ['title' => 'Updated Task', 'description' => 'new', 'status' => 'review', 'priority' => 'urgent', 'due_date' => null, 'position_index' => 1]);
$t = $tasks->findById($taskId);
check('Update task', ($t['title'] ?? '') === 'Updated Task');

$list = $tasks->allForProject($projId);
check('allForProject', count($list) >= 1);

$search = $tasks->allForProject($projId, ['search' => 'Updated']);
check('Search filter', count($search) >= 1);

$stats = $tasks->statsForProject($projId);
check('Stats total=1', ($stats['total'] ?? 0) === 1);

$tasks->softDelete($taskId);
check('Soft delete hides task', $tasks->findById($taskId) === false);

$tasks->restore($taskId);
check('Restore brings back', $tasks->findById($taskId) !== false);
echo PHP_EOL;

// ── 6. ActivityModel ──────────────────────────────────────────
echo "[6] ActivityModel" . PHP_EOL;
$activity = new ActivityModel();
$activity->log($uid, 'test_action', $taskId, $projId, ['key' => 'val']);
$logs = $activity->recentForProject($projId, 5);
check('Log created', count($logs) >= 1);
check('Log has user_name', isset($logs[0]['user_name']));
$myLogs = $activity->recentForUser($uid, 5);
check('recentForUser', count($myLogs) >= 1);
echo PHP_EOL;

// ── 7. Cleanup ────────────────────────────────────────────────
echo "[7] Cleanup" . PHP_EOL;
$db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
check('Cascade delete cleaned', !$users->emailExists($email));
echo PHP_EOL;

$total = $p + $f;
echo str_repeat('-', 48) . PHP_EOL;
echo " Results: {$p}/{$total} passed" . ($f === 0 ? " — ALL PASSED!" : " — {$f} FAILED") . PHP_EOL . PHP_EOL;
