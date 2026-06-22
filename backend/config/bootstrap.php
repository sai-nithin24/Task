<?php
declare(strict_types=1);

// ── Load .env ────────────────────────────────────────────────
require_once __DIR__ . '/env.php';
loadEnv(dirname(__DIR__, 2) . '/.env');

// ── Error handling based on environment ──────────────────────
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ── CORS headers ─────────────────────────────────────────────
$allowedOrigin = rtrim($_ENV['APP_URL'] ?? '*', '/');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Pre-flight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Autoloader ───────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $map = [
        'Database'          => __DIR__ . '/database.php',
        'Router'            => __DIR__ . '/../core/Router.php',
        'Response'          => __DIR__ . '/../core/Response.php',
        'JwtHelper'         => __DIR__ . '/../core/JwtHelper.php',
        'AuthMiddleware'    => __DIR__ . '/../core/AuthMiddleware.php',
        'BaseController'    => __DIR__ . '/../core/BaseController.php',
        'AuthController'    => __DIR__ . '/../controllers/AuthController.php',
        'TaskController'    => __DIR__ . '/../controllers/TaskController.php',
        'ProjectController' => __DIR__ . '/../controllers/ProjectController.php',
        'ActivityController'=> __DIR__ . '/../controllers/ActivityController.php',
        'UserModel'         => __DIR__ . '/../models/UserModel.php',
        'TaskModel'         => __DIR__ . '/../models/TaskModel.php',
        'ProjectModel'      => __DIR__ . '/../models/ProjectModel.php',
        'ActivityModel'     => __DIR__ . '/../models/ActivityModel.php',
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

// ── Global exception handler ─────────────────────────────────
set_exception_handler(function (Throwable $e): void {
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    exit;
});
