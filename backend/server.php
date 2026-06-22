<?php
/**
 * PHP Built-in Server Router
 * Used when running: php -S localhost:8080 backend/server.php
 *
 * Routes all requests to routes/api.php and also serves
 * frontend static files so the whole app works on one port.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ── Serve frontend static files ───────────────────────────────
$frontendRoot = __DIR__ . '/../frontend/public';
$staticFile   = $frontendRoot . $uri;

// Serve index.html for root
if ($uri === '/' || $uri === '') {
    $staticFile = $frontendRoot . '/index.html';
}

// Serve CSS / JS / images directly
if (is_file($staticFile)) {
    $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'html'  => 'text/html; charset=utf-8',
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg',
        'jpeg'  => 'image/jpeg',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    readfile($staticFile);
    return true;
}

// Serve /src/css and /src/js from frontend/src
$srcFile = __DIR__ . '/../frontend' . $uri;
if (is_file($srcFile)) {
    $ext = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        default => 'text/plain',
    };
    header('Content-Type: ' . $mime);
    readfile($srcFile);
    return true;
}

// ── Route all /api/* requests through api.php ─────────────────
// Strip /api prefix if present (the Router handles clean paths)
require_once __DIR__ . '/routes/api.php';
