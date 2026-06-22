<?php
/**
 * TaskFlow — Database Installer
 * Run this file ONCE in your browser or CLI to create the DB + all tables.
 * URL: http://localhost/Enterprise_Task_Manager_Source/backend/setup/install.php
 *
 * After running successfully, DELETE or restrict access to this file.
 */
declare(strict_types=1);

// ── Load .env ────────────────────────────────────────────────
$envPath = dirname(__DIR__, 2) . '/.env';
$env = [];

if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $env[trim($key)] = trim($val);
    }
}

$host    = $env['DB_HOST']    ?? 'localhost';
$port    = $env['DB_PORT']    ?? '3306';
$dbName  = $env['DB_NAME']    ?? 'task_manager';
$user    = $env['DB_USER']    ?? 'root';
$pass    = $env['DB_PASS']    ?? '';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

$steps  = [];
$errors = [];

function ok(string $msg): void  { global $steps;  $steps[]  = $msg; }
function err(string $msg): void { global $errors; $errors[] = $msg; }

// ── Step 1: Connect WITHOUT selecting a database ─────────────
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset={$charset}",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    ok("✅ Connected to MySQL at {$host}:{$port} as <strong>{$user}</strong>");
} catch (PDOException $e) {
    err("❌ Cannot connect to MySQL: " . htmlspecialchars($e->getMessage()));
    render($steps, $errors); exit;
}

// ── Step 2: Create database ───────────────────────────────────
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`
                CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ok("✅ Database <strong>{$dbName}</strong> created (or already exists)");
} catch (PDOException $e) {
    err("❌ Could not create database: " . htmlspecialchars($e->getMessage()));
    render($steps, $errors); exit;
}

// ── Step 3: Select the database ──────────────────────────────
$pdo->exec("USE `{$dbName}`");
ok("✅ Using database <strong>{$dbName}</strong>");

// ── Step 4: Create tables ─────────────────────────────────────
$tables = [];

$tables['users'] = "
CREATE TABLE IF NOT EXISTS users (
  id           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100)     NOT NULL,
  email        VARCHAR(150)     NOT NULL UNIQUE,
  password     VARCHAR(255)     NOT NULL,
  avatar_color VARCHAR(7)       NOT NULL DEFAULT '#6366f1',
  role         ENUM('admin','member') NOT NULL DEFAULT 'member',
  is_active    TINYINT(1)       NOT NULL DEFAULT 1,
  created_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['projects'] = "
CREATE TABLE IF NOT EXISTS projects (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  NOT NULL,
  name        VARCHAR(150)  NOT NULL,
  description TEXT,
  color       VARCHAR(7)    NOT NULL DEFAULT '#6366f1',
  is_archived TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_project_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_project_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['tasks'] = "
CREATE TABLE IF NOT EXISTS tasks (
  id             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  project_id     INT UNSIGNED  NOT NULL,
  assigned_to    INT UNSIGNED  DEFAULT NULL,
  title          VARCHAR(200)  NOT NULL,
  description    TEXT,
  status         ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
  priority       ENUM('low','medium','high','urgent')       NOT NULL DEFAULT 'medium',
  due_date       DATE          DEFAULT NULL,
  position_index INT UNSIGNED  NOT NULL DEFAULT 0,
  is_deleted     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_project
    FOREIGN KEY (project_id) REFERENCES projects (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_task_assigned
    FOREIGN KEY (assigned_to) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_task_project  (project_id),
  INDEX idx_task_status   (status),
  INDEX idx_task_deleted  (is_deleted),
  INDEX idx_task_due      (due_date),
  INDEX idx_task_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['activity_logs'] = "
CREATE TABLE IF NOT EXISTS activity_logs (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED  DEFAULT NULL,
  task_id     INT UNSIGNED  DEFAULT NULL,
  project_id  INT UNSIGNED  DEFAULT NULL,
  action      VARCHAR(60)   NOT NULL,
  meta        JSON          DEFAULT NULL,
  created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_log_task
    FOREIGN KEY (task_id) REFERENCES tasks (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_log_user    (user_id),
  INDEX idx_log_task    (task_id),
  INDEX idx_log_project (project_id),
  INDEX idx_log_action  (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$tables['sessions'] = "
CREATE TABLE IF NOT EXISTS sessions (
  id         VARCHAR(64)   PRIMARY KEY,
  user_id    INT UNSIGNED  NOT NULL,
  token_hash VARCHAR(255)  NOT NULL,
  expires_at DATETIME      NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_session_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_session_user    (user_id),
  INDEX idx_session_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        ok("✅ Table <strong>{$name}</strong> ready");
    } catch (PDOException $e) {
        err("❌ Failed to create table <strong>{$name}</strong>: " . htmlspecialchars($e->getMessage()));
    }
}

// ── Step 5: Verify all tables exist ──────────────────────────
try {
    $stmt   = $pdo->query("SHOW TABLES");
    $found  = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $expect = array_keys($tables);
    $miss   = array_diff($expect, $found);

    if (empty($miss)) {
        ok("✅ All " . count($expect) . " tables verified in database");
    } else {
        err("❌ Missing tables: " . implode(', ', $miss));
    }
} catch (PDOException $e) {
    err("❌ Verification failed: " . htmlspecialchars($e->getMessage()));
}

// ── Step 6: Check .env is loaded ────────────────────────────
if (!empty($env)) {
    ok("✅ .env file loaded (" . count($env) . " variables)");
} else {
    err("⚠️ .env file not found at: " . htmlspecialchars($envPath) . " — using defaults");
}

render($steps, $errors);

// ── Render HTML ───────────────────────────────────────────────
function render(array $steps, array $errors): void {
    $success = empty($errors);
    $title   = $success ? 'Installation Complete ✅' : 'Installation had errors ⚠️';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>TaskFlow Installer</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: linear-gradient(135deg, #4f46e5, #7c3aed);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 2rem;
    }
    .logo {
      width: 40px; height: 40px;
      background: #6366f1;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
    }
    .logo svg { width: 22px; height: 22px; }
    h1 { font-size: 1.1rem; color: #1e1b4b; font-weight: 700; }
    .status-heading {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 1.5rem;
      padding-bottom: .75rem;
      border-bottom: 2px solid #e5e7eb;
      color: <?= $success ? '#065f46' : '#b91c1c' ?>;
    }
    .step-list { list-style: none; display: flex; flex-direction: column; gap: .55rem; margin-bottom: 1.5rem; }
    .step-list li {
      padding: .65rem 1rem;
      background: #f0fdf4;
      border-radius: 8px;
      font-size: .9rem;
      color: #166534;
      border-left: 4px solid #10b981;
    }
    .error-list { list-style: none; display: flex; flex-direction: column; gap: .55rem; margin-bottom: 1.5rem; }
    .error-list li {
      padding: .65rem 1rem;
      background: #fef2f2;
      border-radius: 8px;
      font-size: .9rem;
      color: #b91c1c;
      border-left: 4px solid #ef4444;
    }
    .notice {
      background: #fef9c3;
      border: 1.5px solid #fbbf24;
      border-radius: 10px;
      padding: 1rem 1.25rem;
      font-size: .875rem;
      color: #92400e;
      margin-top: 1.5rem;
    }
    .notice strong { display: block; margin-bottom: .3rem; font-size: .95rem; }
    .next-steps {
      background: #ede9fe;
      border-radius: 10px;
      padding: 1.25rem;
      margin-top: 1.5rem;
    }
    .next-steps h3 { font-size: 1rem; color: #4f46e5; margin-bottom: .75rem; }
    .next-steps ol { padding-left: 1.25rem; display: flex; flex-direction: column; gap: .4rem; font-size: .875rem; color: #4c1d95; }
    code {
      background: #ddd6fe;
      padding: .1rem .4rem;
      border-radius: 4px;
      font-family: monospace;
      font-size: .82rem;
    }
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="logo">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
    </div>
    <h1>TaskFlow Installer</h1>
  </div>

  <h2 class="status-heading"><?= $title ?></h2>

  <?php if (!empty($steps)): ?>
    <ul class="step-list">
      <?php foreach ($steps as $s): ?>
        <li><?= $s ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <ul class="error-list">
      <?php foreach ($errors as $e): ?>
        <li><?= $e ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="notice">
      <strong>⚠️ Security Notice</strong>
      Delete or restrict access to <code>backend/setup/install.php</code> after installation.
      It should not be publicly accessible on a live server.
    </div>
    <div class="next-steps">
      <h3>Next Steps</h3>
      <ol>
        <li>Open <code>frontend/src/js/app.js</code> and confirm <code>API_BASE</code> matches your server URL</li>
        <li>Open <code>frontend/public/index.html</code> in your browser</li>
        <li>Register a new account and start creating projects and tasks</li>
        <li>Delete <code>backend/setup/install.php</code> when done</li>
      </ol>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
    <?php
}
