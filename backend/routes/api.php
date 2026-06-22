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

$router->dispatch();
