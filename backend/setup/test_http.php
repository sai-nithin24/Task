<?php
/**
 * TaskFlow HTTP Integration Tests
 * Uses PHP curl to make real HTTP requests to the running server.
 * Run: php backend/setup/test_http.php
 */
declare(strict_types=1);

$BASE = 'http://localhost:8080/api';
$p = 0; $f = 0;

function req(string $method, string $url, array $body = [], string $token = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_filter([
            'Content-Type: application/json',
            'Accept: application/json',
            $token ? "Authorization: Bearer {$token}" : '',
        ]),
        CURLOPT_POSTFIELDS     => $body ? json_encode($body) : null,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $raw    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close deprecated in PHP 8.5 — suppress notice
    @curl_close($ch);
    $json = json_decode($raw ?: '{}', true) ?? [];
    $json['__status'] = $status;
    return $json;
}

function ok(string $label, bool $result, string $detail = ''): void
{
    global $p, $f;
    $icon = $result ? 'PASS' : 'FAIL';
    $color = $result ? "\033[32m" : "\033[31m";
    echo "  {$color}{$icon}\033[0m  {$label}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
    if ($result) $p++; else $f++;
}

echo PHP_EOL . "\033[1m TaskFlow HTTP Test Suite\033[0m" . PHP_EOL;
echo str_repeat('─', 50) . PHP_EOL . PHP_EOL;

// ── 1. Health ──────────────────────────────────────────────────
echo "\033[1m[1] Health\033[0m" . PHP_EOL;
$r = req('GET', "{$BASE}/health");
ok('GET /health returns ok',  ($r['data']['status'] ?? '') === 'ok', "HTTP {$r['__status']}");
echo PHP_EOL;

// ── 2. Auth ────────────────────────────────────────────────────
echo "\033[1m[2] Auth\033[0m" . PHP_EOL;
$email = 'http_test_' . time() . '@tf.test';

$r = req('POST', "{$BASE}/auth/register", ['name' => 'HTTP Test', 'email' => $email, 'password' => 'Test@1234']);
ok('POST /auth/register', ($r['success'] ?? false) === true, "HTTP {$r['__status']}");
$token = $r['data']['token'] ?? '';
ok('Token returned',      strlen($token) > 20);

$r = req('POST', "{$BASE}/auth/login", ['email' => $email, 'password' => 'Test@1234']);
ok('POST /auth/login',    ($r['success'] ?? false) === true, "HTTP {$r['__status']}");

$r = req('POST', "{$BASE}/auth/login", ['email' => $email, 'password' => 'wrongpass']);
ok('Wrong password → 401', ($r['__status'] ?? 0) === 401);

$r = req('GET', "{$BASE}/auth/me", [], $token);
ok('GET /auth/me',        ($r['data']['user']['name'] ?? '') === 'HTTP Test');

$r = req('GET', "{$BASE}/projects", [], 'INVALIDTOKEN');
ok('Bad token → 401',     ($r['__status'] ?? 0) === 401);

$r = req('GET', "{$BASE}/projects");
ok('No token → 401',      ($r['__status'] ?? 0) === 401);
echo PHP_EOL;

// ── 3. Projects ────────────────────────────────────────────────
echo "\033[1m[3] Projects\033[0m" . PHP_EOL;

$r = req('POST', "{$BASE}/projects", ['name' => 'HTTP Project', 'color' => '#6366f1'], $token);
ok('POST /projects',      ($r['success'] ?? false) === true, "HTTP {$r['__status']}");
$projId = $r['data']['project']['id'] ?? 0;
ok('Project ID returned', $projId > 0, "id={$projId}");

$r = req('POST', "{$BASE}/projects", [], $token);
ok('Empty name → 422',    ($r['__status'] ?? 0) === 422);

$r = req('GET', "{$BASE}/projects", [], $token);
ok('GET /projects',       count($r['data']['projects'] ?? []) >= 1);

$r = req('PUT', "{$BASE}/projects/{$projId}", ['name' => 'Updated HTTP Project', 'color' => '#10b981'], $token);
ok('PUT /projects/:id',   ($r['success'] ?? false) === true);

$r = req('GET', "{$BASE}/projects/{$projId}", [], $token);
ok('GET /projects/:id',   ($r['data']['project']['name'] ?? '') === 'Updated HTTP Project');
echo PHP_EOL;

// ── 4. Tasks ───────────────────────────────────────────────────
echo "\033[1m[4] Tasks\033[0m" . PHP_EOL;

$r = req('POST', "{$BASE}/projects/{$projId}/tasks", [
    'title' => 'HTTP Task', 'description' => 'Test desc',
    'priority' => 'high', 'status' => 'todo', 'due_date' => date('Y-m-d', strtotime('+7 days'))
], $token);
ok('POST tasks',          ($r['success'] ?? false) === true, "HTTP {$r['__status']}");
$taskId = $r['data']['task']['id'] ?? 0;
ok('Task ID returned',    $taskId > 0, "id={$taskId}");

$r = req('POST', "{$BASE}/projects/{$projId}/tasks", [], $token);
ok('Empty title → 422',   ($r['__status'] ?? 0) === 422);

$r = req('GET', "{$BASE}/tasks/{$taskId}", [], $token);
ok('GET /tasks/:id',      ($r['data']['task']['title'] ?? '') === 'HTTP Task');

$r = req('PUT', "{$BASE}/tasks/{$taskId}", [
    'title' => 'Updated HTTP Task', 'description' => 'new', 'status' => 'in_progress', 'priority' => 'urgent'
], $token);
ok('PUT /tasks/:id',      ($r['success'] ?? false) === true);

$r = req('PATCH', "{$BASE}/tasks/{$taskId}/status", ['status' => 'review'], $token);
ok('PATCH /tasks/:id/status', ($r['success'] ?? false) === true);

$r = req('PATCH', "{$BASE}/tasks/{$taskId}/status", ['status' => 'invalid'], $token);
ok('Invalid status → 422', ($r['__status'] ?? 0) === 422);

$r = req('GET', "{$BASE}/projects/{$projId}/tasks", [], $token);
ok('GET tasks list',      ($r['data']['stats']['total'] ?? 0) === 1, "total={$r['data']['stats']['total']}");

$r = req('GET', "{$BASE}/projects/{$projId}/tasks?search=Updated+HTTP", [], $token);
ok('Search filter',       count($r['data']['tasks'] ?? []) >= 1);

$r = req('GET', "{$BASE}/projects/{$projId}/tasks?priority=urgent", [], $token);
ok('Priority filter',     count($r['data']['tasks'] ?? []) >= 1);

$r = req('GET', "{$BASE}/projects/{$projId}/tasks?status=review", [], $token);
ok('Status filter',       count($r['data']['tasks'] ?? []) >= 1);
echo PHP_EOL;

// ── 5. Delete + Restore ────────────────────────────────────────
echo "\033[1m[5] Soft Delete & Restore\033[0m" . PHP_EOL;

$r = req('DELETE', "{$BASE}/tasks/{$taskId}", [], $token);
ok('DELETE /tasks/:id',   ($r['success'] ?? false) === true);

$r = req('GET', "{$BASE}/projects/{$projId}/tasks", [], $token);
ok('Hidden after delete',  ($r['data']['stats']['total'] ?? -1) === 0);

$r = req('PATCH', "{$BASE}/tasks/{$taskId}/restore", [], $token);
ok('PATCH restore',       ($r['success'] ?? false) === true);

$r = req('GET', "{$BASE}/projects/{$projId}/tasks", [], $token);
ok('Visible after restore', ($r['data']['stats']['total'] ?? -1) === 1);
echo PHP_EOL;

// ── 6. Activity ────────────────────────────────────────────────
echo "\033[1m[6] Activity Logs\033[0m" . PHP_EOL;
$r = req('GET', "{$BASE}/projects/{$projId}/activity", [], $token);
ok('Project activity',    count($r['data']['logs'] ?? []) >= 1, count($r['data']['logs'] ?? []) . ' entries');

$r = req('GET', "{$BASE}/activity/me", [], $token);
ok('My activity',         count($r['data']['logs'] ?? []) >= 1);
echo PHP_EOL;

// ── 7. Project delete ──────────────────────────────────────────
echo "\033[1m[7] Project Delete\033[0m" . PHP_EOL;
$r = req('DELETE', "{$BASE}/projects/{$projId}", [], $token);
ok('DELETE /projects/:id', ($r['success'] ?? false) === true);

$r = req('GET', "{$BASE}/projects/{$projId}", [], $token);
ok('Project gone → 404',  ($r['__status'] ?? 0) === 404);
echo PHP_EOL;

// ── 8. Frontend assets ─────────────────────────────────────────
echo "\033[1m[8] Frontend Assets\033[0m" . PHP_EOL;
$ch = curl_init('http://localhost:8080/');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Accept: text/html']]);
$html = curl_exec($ch);
$htmlStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ok('HTML loads (200)',      $htmlStatus === 200);
ok('Title = TaskFlow',     str_contains($html, 'TaskFlow'));
ok('confirm-modal hidden', str_contains($html, 'id="confirm-modal"') && str_contains($html, 'style="display:none"'));
ok('No bare hidden attr',  !preg_match('/id="(task-modal|project-modal|confirm-modal)"[^>]+\bhidden\b/', $html));

$ch = curl_init('http://localhost:8080/src/css/styles.css');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$css = curl_exec($ch);
$cssStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ok('CSS loads (200)',       $cssStatus === 200);
ok('CSS modal display none',  str_contains($css, 'display: none') || str_contains($css, 'display:none'));
ok('CSS has is-open',       str_contains($css, 'is-open'));

$ch = curl_init('http://localhost:8080/src/js/app.js');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$js = curl_exec($ch);
$jsStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ok('JS loads (200)',         $jsStatus === 200);
ok('JS > 5000 bytes',       strlen($js) > 5000, strlen($js) . ' bytes');
ok('No dup confirmDelete',  substr_count($js, 'function confirmDelete') + substr_count($js, 'function openConfirmDelete') === 1);
ok('No dup pendingDeleteId', substr_count($js, 'pendingDeleteId') >= 1 && substr_count($js, 'let pendingDeleteId') + substr_count($js, 'state.pendingDeleteId') >= 1);
ok('openModal present',     str_contains($js, 'function openModal'));
ok('closeModal present',    str_contains($js, 'function closeModal'));
echo PHP_EOL;

// ── Summary ────────────────────────────────────────────────────
$total = $p + $f;
echo str_repeat('─', 50) . PHP_EOL;
if ($f === 0) echo "\033[32m Results: {$p}/{$total} — ALL PASSED! 🎉\033[0m" . PHP_EOL;
else          echo "\033[31m Results: {$p}/{$total} — {$f} FAILED\033[0m" . PHP_EOL;
echo PHP_EOL;
