-- ============================================================
-- Pyo Base System - Database Schema
-- A reusable starting point: session auth, roles, per-role/per-account
-- module access, notifications, audit log, self-service profile.
-- ============================================================

CREATE DATABASE IF NOT EXISTS pyobasesystem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pyobasesystem_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notification_reads;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_module_access;
DROP TABLE IF EXISTS role_module_access;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users. full_name is kept in sync (recomputed) whenever the split name
-- fields change — it's what the sidebar/topbar/session/audit log read.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(50) NULL,
    middle_name VARCHAR(50) NULL,
    last_name VARCHAR(50) NULL,
    suffix VARCHAR(20) NULL,
    department VARCHAR(150) NULL,
    last_login DATETIME NULL,
    avatar_path VARCHAR(255) NULL,
    role ENUM('super_admin','admin','user') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Per-role sidebar/page access. Missing (role, module_key) rows default to
-- allowed=true in application code, so an empty table means "no restrictions".
CREATE TABLE role_module_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('super_admin','admin','user') NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    can_access TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY role_module (role, module_key)
);

-- Per-account access overrides. A row here wins over the role default in
-- role_module_access for that one user; no row means "inherit the role default".
CREATE TABLE user_module_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_key VARCHAR(50) NOT NULL,
    can_access TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_module (user_id, module_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications are broadcast per-role (e.g. call notifyRoles(['admin'], ...)
-- from any feature code you add); read state is tracked per-user in
-- notification_reads so each recipient can dismiss independently.
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('super_admin','admin','user') NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NULL,
    link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE notification_reads (
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default super_admin — username: admin / password: admin123
-- CHANGE THIS PASSWORD after first login (via My Profile).
INSERT INTO users (username, password, full_name, first_name, last_name, role) VALUES
('admin', '$2y$10$0w832hH3EFlb4KMRhAW6zOnCB9MVpvgCg8nP54Wkc/.mVdev4LG/u', 'System Administrator', 'System', 'Administrator', 'super_admin');
