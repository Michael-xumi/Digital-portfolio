-- Database Schema for Online Portfolio System
-- This script handles both fresh installs and migrations from the old schema.
-- Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS guards).

-- Users table: Stores admin and visitor accounts
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- Store hashed passwords
    role ENUM('Administrator', 'Visitor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add email column if it doesn't exist (for existing tables)
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) UNIQUE;

-- Categories table: Hierarchical structure for Years, Semesters, Periods
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    parent_id INT DEFAULT NULL, -- For hierarchy (e.g., Year 1 -> Semester 1)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Files table: Stores file metadata
-- For fresh installs this creates the final schema directly.
-- For existing databases the ALTER statements below handle migration.
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) DEFAULT NULL,
    status ENUM('Submitted', 'Approved', 'Rejected', 'Modification Required') DEFAULT 'Submitted',
    is_active BOOLEAN DEFAULT TRUE,
    current_version_id INT DEFAULT NULL,
    category_id INT,
    uploaded_by INT, -- User ID of uploader (admin)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- File Versions table: Stores every version of an uploaded file
CREATE TABLE IF NOT EXISTS file_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    version_number INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_file_version (file_id, version_number)
);

-- Admin Comments table: Private comments only visible to admins
CREATE TABLE IF NOT EXISTS admin_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    admin_id INT NOT NULL,
    comment TEXT NOT NULL,
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Permissions table: Links visitors to specific files
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Visitor user ID
    file_id INT NOT NULL,
    permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (user_id, file_id) -- Prevent duplicate permissions
);

-- ============================================================
-- MIGRATION: Safely upgrade existing databases
-- These statements are harmless on fresh installs.
-- ============================================================

-- Add new columns to files table if they don't exist yet
ALTER TABLE files ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE files ADD COLUMN IF NOT EXISTS current_version_id INT DEFAULT NULL;

-- Add permission_level to permissions if it doesn't exist
ALTER TABLE permissions ADD COLUMN IF NOT EXISTS permission_level ENUM('read', 'write', 'admin') DEFAULT 'read';

-- Migrate old status values: convert 'active'/'inactive' to the new schema
-- Step 1: Map old visibility values to the is_active boolean
UPDATE files SET is_active = TRUE WHERE status = 'active';
UPDATE files SET is_active = FALSE WHERE status = 'inactive';

-- Step 2: Temporarily widen the enum to include both old and new values
ALTER TABLE files MODIFY COLUMN status ENUM('active', 'inactive', 'Submitted', 'Approved', 'Rejected', 'Modification Required') DEFAULT 'Submitted';

-- Step 3: Convert old status values to new workflow values
UPDATE files SET status = 'Approved' WHERE status IN ('active', 'inactive');

-- Step 4: Now safely narrow the enum to only the new workflow values
ALTER TABLE files MODIFY COLUMN status ENUM('Submitted', 'Approved', 'Rejected', 'Modification Required') DEFAULT 'Submitted';

-- Migrate existing file_path entries into file_versions as version 1
INSERT IGNORE INTO file_versions (file_id, version_number, file_path, uploaded_by, created_at)
SELECT id, 1, file_path, uploaded_by, created_at FROM files
WHERE file_path IS NOT NULL AND file_path != ''
AND id NOT IN (SELECT file_id FROM file_versions);

-- Point current_version_id to the migrated version 1 entries
UPDATE files f
JOIN file_versions fv ON f.id = fv.file_id AND fv.version_number = 1
SET f.current_version_id = fv.id
WHERE f.current_version_id IS NULL;

-- ============================================================
-- SAMPLE DATA (safe to re-run, uses ON DUPLICATE KEY / INSERT IGNORE)
-- ============================================================

-- Admin user (username: admin, password: password)
INSERT INTO users (username, email, password, role) VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Visitor users (username: visitor1/visitor2, password: password)
INSERT INTO users (username, email, password, role) VALUES ('visitor1', 'visitor1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Visitor')
ON DUPLICATE KEY UPDATE email = VALUES(email);
INSERT INTO users (username, email, password, role) VALUES ('visitor2', 'visitor2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Visitor')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- ============================================================
-- SAMPLE CATEGORIES with explicit IDs to preserve file links.
-- Uses INSERT IGNORE so re-running is safe and IDs are stable.
-- ============================================================
INSERT IGNORE INTO categories (id, name, parent_id) VALUES
    (194, 'Year 1: IT Fundamentals - NHL Stenden (2025-2026)', NULL),
    (195, 'Semester 1: Foundation Modules (Periods 1 & 2)', 194),
    (196, 'Period 1: Web Development Basics (HTML, CSS, PHP, Figma)', 195),
    (197, 'Period 1 Professional Skills & Documentation', 195),
    (198, 'Period 2: Database Management (MySQL, SQL, Proxmox)', 195),
    (199, 'Period 2 Professional Skills & Documentation', 195),
    (200, 'Semester 2: Future Modules (Periods 3 & 4)', 194),
    (201, 'Tools & Version Control (GitHub, Docker, VS Code)', 194),
    (202, 'Period 3: Object-oriented Programming (Java, BattleBot)', 200),
    (203, 'Period 3 Professional Skills & Documentation', 200),
    (204, 'Period 4: Project Innovate (Computational Thinking, Project Management)', 200),
    (205, 'Period 4 Professional Skills & Documentation', 200),
    (212, 'Year 2: Intermediate Studies (Placeholder)', NULL);

-- Note: In production, use proper password hashing (e.g., password_hash() in PHP)
-- Also, adjust file paths and permissions as needed.