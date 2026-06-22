-- ============================================================
--  Enterprise Task Manager — Database Schema
--  Engine: MySQL 8.0+  |  Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS task_manager
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE task_manager;

-- ── Users ────────────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Projects ─────────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tasks ─────────────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Activity Logs ─────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sessions ──────────────────────────────────────────────────
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
