<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$router = new Router();

// ── Auth ─────────────────────────────────────────────────────
$router->post('/auth/register', function () {
    (new AuthController())->register();
});

$router->post('/auth/login', function () {
    (new AuthController())->login();
});

$router->get('/auth/me', function () {
    (new AuthController())->me();
});

// ── Projects ─────────────────────────────────────────────────
$router->get('/projects', function () {
    (new ProjectController())->index();
});

$router->post('/projects', function () {
    (new ProjectController())->store();
});

$router->get('/projects/:id', function (array $p) {
    (new ProjectController())->show($p);
});

$router->put('/projects/:id', function (array $p) {
    (new ProjectController())->update($p);
});

$router->delete('/projects/:id', function (array $p) {
    (new ProjectController())->destroy($p);
});

// ── Tasks ─────────────────────────────────────────────────────
$router->get('/projects/:project_id/tasks', function (array $p) {
    (new TaskController())->index($p);
});

$router->post('/projects/:project_id/tasks', function (array $p) {
    (new TaskController())->store($p);
});

$router->get('/tasks/:id', function (array $p) {
    (new TaskController())->show($p);
});

$router->put('/tasks/:id', function (array $p) {
    (new TaskController())->update($p);
});

$router->patch('/tasks/:id/status', function (array $p) {
    (new TaskController())->updateStatus($p);
});

$router->patch('/tasks/:id/reorder', function (array $p) {
    (new TaskController())->reorder($p);
});

$router->delete('/tasks/:id', function (array $p) {
    (new TaskController())->destroy($p);
});

$router->patch('/tasks/:id/restore', function (array $p) {
    (new TaskController())->restore($p);
});

// ── Activity ─────────────────────────────────────────────────
$router->get('/projects/:project_id/activity', function (array $p) {
    (new ActivityController())->forProject($p);
});

$router->get('/activity/me', function () {
    (new ActivityController())->forMe();
});

// ── Health check ─────────────────────────────────────────────
$router->get('/health', function () {
    Response::success(['status' => 'ok', 'time' => date('c')]);
});

// ── Firebase diagnostics (remove after confirming connection works) ───
$router->get('/debug/firebase', function () {
    $projectId  = $_ENV['FIREBASE_PROJECT_ID']   ?? 'NOT SET';
    $email      = $_ENV['FIREBASE_CLIENT_EMAIL']  ?? 'NOT SET';
    $key        = $_ENV['FIREBASE_PRIVATE_KEY']   ?? 'NOT SET';

    // Check key format
    $keyLoaded  = false;
    $keyError   = '';
    if ($key !== 'NOT SET') {
        $res = openssl_pkey_get_private($key);
        if ($res) {
            $keyLoaded = true;
        } else {
            $keyError = openssl_error_string() ?: 'unknown openssl error';
        }
    }

    Response::success([
        'project_id_set'    => $projectId !== 'NOT SET',
        'project_id'        => $projectId,
        'email_set'         => $email !== 'NOT SET',
        'email'             => $email,
        'key_set'           => $key !== 'NOT SET',
        'key_first_30'      => substr($key, 0, 30),
        'key_loaded_ok'     => $keyLoaded,
        'key_error'         => $keyError,
        'key_has_newlines'  => substr_count($key, "\n"),
        'key_has_literal_n' => substr_count($key, '\n'),
    ]);
});

$router->dispatch();
