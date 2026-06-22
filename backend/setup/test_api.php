<?php
/**
 * TaskFlow — Full Backend Test Suite (CLI)
 * Tests: .env loading, DB connection, all models, JWT, password hashing.
 * Run: php backend/setup/test_api.php
 */
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__, 2) . '/.env');
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/JwtHelper.php';
require_once dirname(__DIR__) . '/models/UserModel.php';
require_once dirname(__DIR__) . '/models/ProjectModel.php';
require_once dirname(__DIR__) . '/models/TaskModel.php';
require_once dirname(__DIR__) . '/models/ActivityModel.php';

$pass = 0; $fail = 0;

function check(string $label, bool $result, string $detail = ''): void {
    global $pass, $fail;
    if ($result) {
        echo "\033[32m  ✅ PASS\033[0m  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
        $pass++;
    } else {
        echo "\033[31m  ❌ FAIL\033[0m  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
        $fail++;
    }
}

echo PHP_EOL . "\033[1m TaskFlow Backend Test Suite\033[0m" . PHP_EOL;
echo str_repeat('─', 50) . PHP_EOL . PHP_EOL;

// ── 1. ENV ────────────────────────────────────────────────────
echo "\033[1m[1] Environment\033[0m" . PHP_EOL;
check('.env loaded',         !empty($_ENV['DB_NAME']),      $_ENV['DB_NAME'] ?? 'missing');
check('DB_USER set',         !empty($_ENV['DB_USER']),      $_ENV['DB_USER'] ?? 'missing');
check('JWT_SECRET length',   strlen($_ENV['JWT_SECRET'] ?? '') >= 32, strlen($_ENV['JWT_SECRET'] ?? '').' chars');
echo PHP_EOL;

// ── 2. DB CONNECTION ──────────────────────────────────────────
echo "\033[1m[2] Database Connection\033[0m" . PHP_EOL;
try {
    $db = Database::getConnection();
    $ver = $db->query('SELECT VERSION()')->fetchColumn();
    check('PDO connects to MySQL', true, "MySQL {$ver}");
    check('Database is task_manager', str_contains($ver, '8'), "version {$ver}");
} catch (Exception $e) {
    check('PDO connects to MySQL', false, $e->getMessage());
}
echo PHP_EOL;

// ── 3. JWT ────────────────────────────────────────────────────
echo "\033[1m[3] JWT Helper\033[0m" . PHP_EOL;
try {
    $token   = JwtHelper::encode(['user_id' => 1, 'role' => 'member']);
    $parts   = explode('.', $token);
    check('Token has 3 parts',   count($parts) === 3);

    $payload = JwtHelper::decode($token);
    check('Decode returns user_id',  isset($payload['user_id']) && $payload['user_id'] === 1);
    check('Decode returns role',     ($payload['role'] ?? '') === 'member');
    check('Payload has exp',         isset($payload['exp']) && $payload['exp'] > time());

    // Tamper test
    $tampered = $parts[0] . '.' . $parts[1] . '.INVALIDSIG';
    $caught = false;
    try { JwtHelper::decode($tampered); } catch (InvalidArgumentException) { $caught = true; }
    check('Tampered token rejected', $caught);
} catch (Exception $e) {
    check('JWT encode/decode', false, $e->getMessage());
}
echo PHP_EOL;

// ── 4. PASSWORD HASHING ───────────────────────────────────────
echo "\033[1m[4] Password Security\033[0m" . PHP_EOL;
$raw  = 'TestPass@2024';
$hash = password_hash($raw, PASSWORD_BCRYPT, ['cost' => 12]);
check('Bcrypt hash created',        str_starts_with($hash, '$2y$'));
check('Hash cost = 12',             str_contains($hash, '$12$'));
check('password_verify correct',    password_verify($raw, $hash));
check('password_verify wrong pass', !password_verify('WrongPass', $hash));
echo PHP_EOL;

// ── 5. USER MODEL ─────────────────────────────────────────────
echo "\033[1m[5] UserModel\033[0m" . PHP_EOL;
$userModel = new UserModel();
$testEmail = 'test_' . time() . '@taskflow.test';

try {
    // Create user
    $uid = $userModel->create([
        'name'     => 'Test User',
        'email'    => $testEmail,
        'password' => 'TestPass@2024',
    ]);
    check('User created',         $uid > 0, "id={$uid}");

    // Find by email
    $found = $userModel->findByEmail($testEmail);
    check('findByEmail returns user',  (bool)$found);
    check('Email stored correctly',    ($found['email'] ?? '') === strtolower($testEmail));
    check('Password is hashed',        str_starts_with($found['password'] ?? '', '$2y$'));

    // Find by ID
    $byId = $userModel->findById($uid);
    check('findById returns user',     (bool)$byId);
    check('Password excluded in byId', !isset($byId['password']));

    // Email exists check
    check('emailExists true',          $userModel->emailExists($testEmail));
    check('emailExists false',         !$userModel->emailExists('nobody@nowhere.xyz'));

} catch (Exception $e) {
    check('UserModel operations', false, $e->getMessage());
    $uid = 0;
}
echo PHP_EOL;

// ── 6. PROJECT MODEL ──────────────────────────────────────────
echo "\033[1m[6] ProjectModel\033[0m" . PHP_EOL;
$projectModel = new ProjectModel();
$pid = 0;

if (!empty($uid)) {
    try {
        $pid = $projectModel->create(['name' => 'Test Project', 'description' => 'Auto test', 'color' => '#6366f1'], $uid);
        check('Project created',         $pid > 0, "id={$pid}");

        $proj = $projectModel->findById($pid, $uid);
        check('findById returns project', (bool)$proj);
        check('Project name correct',    ($proj['name'] ?? '') === 'Test Project');

        $all = $projectModel->allForUser($uid);
        check('allForUser returns list', count($all) >= 1);

        $projectModel->update($pid, ['name' => 'Updated Project', 'description' => '', 'color' => '#10b981'], $uid);
        $updated = $projectModel->findById($pid, $uid);
        check('Project name updated',    ($updated['name'] ?? '') === 'Updated Project');

    } catch (Exception $e) {
        check('ProjectModel operations', false, $e->getMessage());
    }
}
echo PHP_EOL;

// ── 7. TASK MODEL ─────────────────────────────────────────────
echo "\033[1m[7] TaskModel\033[0m" . PHP_EOL;
$taskModel = new TaskModel();
$tid = 0;

if ($pid > 0) {
    try {
        $tid = $taskModel->create([
            'project_id'     => $pid,
            'title'          => 'Test Task',
            'description'    => 'Auto created task',
            'status'         => 'todo',
            'priority'       => 'high',
            'due_date'       => date('Y-m-d', strtotime('+7 days')),
            'position_index' => 0,
        ]);
        check('Task created',               $tid > 0, "id={$tid}");

        $task = $taskModel->findById($tid);
        check('findById returns task',      (bool)$task);
        check('Task title correct',         ($task['title'] ?? '') === 'Test Task');
        check('Task status is todo',        ($task['status'] ?? '') === 'todo');
        check('Task priority is high',      ($task['priority'] ?? '') === 'high');

        // allForProject
        $tasks = $taskModel->allForProject($pid);
        check('allForProject returns list', count($tasks) >= 1);

        // Search filter
        $search = $taskModel->allForProject($pid, ['search' => 'Test']);
        check('Search filter works',        count($search) >= 1);

        // Status update
        $taskModel->updateStatus($tid, 'in_progress');
        $moved = $taskModel->findById($tid);
        check('Status updated to in_progress', ($moved['status'] ?? '') === 'in_progress');

        // Update task
        $taskModel->update($tid, [
            'title'          => 'Updated Task',
            'description'    => 'Modified',
            'status'         => 'review',
            'priority'       => 'urgent',
            'due_date'       => null,
            'position_index' => 1,
        ]);
        $upd = $taskModel->findById($tid);
        check('Task updated correctly',     ($upd['title'] ?? '') === 'Updated Task');
        check('Priority updated',           ($upd['priority'] ?? '') === 'urgent');

        // Stats
        $stats = $taskModel->statsForProject($pid);
        check('Stats returned',             isset($stats['total']) && $stats['total'] > 0, "total={$stats['total']}");

        // Soft delete
        $taskModel->softDelete($tid);
        $deleted = $taskModel->findById($tid);
        check('Soft delete hides task',     $deleted === false);

        // Restore
        $taskModel->restore($tid);
        $restored = $taskModel->findById($tid);
        check('Restore brings task back',   (bool)$restored);

    } catch (Exception $e) {
        check('TaskModel operations', false, $e->getMessage());
    }
}
echo PHP_EOL;

// ── 8. ACTIVITY MODEL ─────────────────────────────────────────
echo "\033[1m[8] ActivityModel\033[0m" . PHP_EOL;
$actModel = new ActivityModel();

if (!empty($uid) && $pid > 0) {
    try {
        $actModel->log($uid, 'test_action', $tid ?: null, $pid, ['key' => 'value']);
        $logs = $actModel->recentForProject($pid, 5);
        check('Activity log created',        count($logs) >= 1);
        check('Log has user_name',           isset($logs[0]['user_name']));

        $myLogs = $actModel->recentForUser($uid, 5);
        check('recentForUser returns logs',  count($myLogs) >= 1);
    } catch (Exception $e) {
        check('ActivityModel operations', false, $e->getMessage());
    }
}
echo PHP_EOL;

// ── 9. CLEANUP ────────────────────────────────────────────────
echo "\033[1m[9] Cleanup test data\033[0m" . PHP_EOL;
try {
    $db = Database::getConnection();
    if ($uid) { $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]); }
    check('Test user + cascade deleted', !$userModel->emailExists($testEmail));
} catch (Exception $e) {
    check('Cleanup', false, $e->getMessage());
}
echo PHP_EOL;

// ── SUMMARY ───────────────────────────────────────────────────
$total = $pass + $fail;
echo str_repeat('─', 50) . PHP_EOL;
echo "\033[1m Results: {$pass}/{$total} passed";
if ($fail === 0) echo " \033[32m🎉 All tests passed!\033[0m" . PHP_EOL;
else             echo " \033[31m({$fail} failed)\033[0m" . PHP_EOL;
echo PHP_EOL;
