-- Database Schema for Online Portfolio System
-- Run this script in your MySQL database to create the required tables

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
CREATE TABLE IF NOT EXISTS files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(500) NOT NULL, -- Path to uploaded file
    status ENUM('active', 'inactive') DEFAULT 'active',
    category_id INT,
    uploaded_by INT, -- User ID of uploader (admin)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Permissions table: Links visitors to specific files
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Visitor user ID
    file_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (user_id, file_id) -- Prevent duplicate permissions
);

-- Insert sample data (optional, for testing)
-- Admin user (username: admin, password: password)
INSERT INTO users (username, email, password, role) VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Visitor users (username: visitor1/visitor2, password: password)
INSERT INTO users (username, email, password, role) VALUES ('visitor1', 'visitor1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Visitor')
ON DUPLICATE KEY UPDATE email = VALUES(email);
INSERT INTO users (username, email, password, role) VALUES ('visitor2', 'visitor2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Visitor')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Remove duplicate categories (keep the one with smallest id)
DELETE c1 FROM categories c1
INNER JOIN categories c2 
WHERE c1.id > c2.id AND c1.name = c2.name AND c1.parent_id <=> c2.parent_id;

-- Remove empty Year 1 category
DELETE FROM categories WHERE name = 'Year 1: IT Fundamentals - NHL Stenden (2025-2026)' AND parent_id IS NULL;

-- Sample categories (Year 1 -> Semester 1 -> Periods)
INSERT IGNORE INTO categories (name, parent_id) VALUES ('Year 1: IT Fundamentals - NHL Stenden (2025-2026)', NULL);
SET @year1_id = (SELECT id FROM categories WHERE name = 'Year 1: IT Fundamentals - NHL Stenden (2025-2026)' LIMIT 1);

-- INSERT IGNORE INTO categories (name, parent_id) VALUES ('Semester 1: Foundation Modules (Periods 1 & 2)', @year1_id);
-- SET @sem1_id = (SELECT id FROM categories WHERE name = 'Semester 1: Foundation Modules (Periods 1 & 2)' AND parent_id = @year1_id LIMIT 1);

-- INSERT IGNORE INTO categories (name, parent_id) VALUES ('Period 1: Web Development Basics (HTML, CSS, PHP, Figma)', @sem1_id);
INSERT IGNORE INTO categories (name, parent_id) VALUES ('Period 1 Professional Skills & Documentation', @sem1_id);
INSERT IGNORE INTO categories (name, parent_id) VALUES ('Period 2: Database Management (MySQL, SQL, Proxmox)', @sem1_id);
INSERT IGNORE INTO categories (name, parent_id) VALUES ('Period 2 Professional Skills & Documentation', @sem1_id);

INSERT IGNORE INTO categories (name, parent_id) VALUES ('Semester 2: Future Modules (Periods 3 & 4)', @year1_id);
SET @sem2_id = (SELECT id FROM categories WHERE name = 'Semester 2: Future Modules (Periods 3 & 4)' AND parent_id = @year1_id LIMIT 1);

INSERT IGNORE INTO categories (name, parent_id) VALUES ('Tools & Version Control (GitHub, Docker, VS Code)', @sem2_id);

INSERT IGNORE INTO categories (name, parent_id) VALUES ('Year 2: Intermediate Studies (Placeholder)', NULL);

-- Sample files for Period 1
-- INSERT INTO files (title, description, file_path, category_id, uploaded_by) VALUES 
-- ('Week 1: HTML Layout & Figma Analysis', 'Assignment: Website Layout. Project: Analysis Board.', 'https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Week-1-HTML-Layout-Analysis-Report', @period1_id, 1),
-- ('Week 2: Responsive CSS & Figma Design Start', 'Assignment: Grids and Flexbox. Project: Figma Design (Phase 1).', 'https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Week-2-Responsive-CSS-Files', @period1_id, 1),
-- ('Week 3: Simple Website Construction', 'Assignment: Simple Website. Project: Figma Design (Phase 2).', 'https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Week-3-Simple-Website-Files', @period1_id, 1),
-- ('Week 4: Advanced CSS Techniques', 'Assignment: Grids and Flexbox (Review). Project: Figma Design (Phase 3).', 'https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Week-4-Advanced-CSS-Components', @period1_id, 1),
-- ('Week 5: Forms & PHP Integration', 'Assignment: Creating Forms. Project: Figma Design (Final Mockup).', 'https://github.com/Michael-xumi/YOUR-NEW-REPOSITORY-NAME/Week-5-Forms-and-PHP-Files', @period1_id, 1),
-- ('Week 6: PROJECT MILESTONE 1 (Coding in VS Code)', 'Project: Client Website Implementation. (Check Group GitHub)', 'https://github.com/JustinasLaunikonis/Sunny-Socks', @period1_id, 1),
-- ('Week 7: PHP/JS Implementation', 'Project: Website Backend & Interactivity. (Check Group GitHub)', 'https://github.com/JustinasLaunikonis/Sunny-Socks', @period1_id, 1),
-- ('Week 8: FINAL SUBMISSION & Presentation', 'Project: Sunny Socks Final Delivery and Presentation.', 'https://github.com/JustinasLaunikonis/Sunny-Socks', @period1_id, 1);

-- Grant permissions to visitors for the sample files
-- INSERT IGNORE INTO permissions (user_id, file_id) 
-- SELECT 2, id FROM files WHERE category_id = @period1_id; -- visitor1 (id=2)
-- INSERT IGNORE INTO permissions (user_id, file_id) 
-- SELECT 3, id FROM files WHERE category_id = @period1_id; -- visitor2 (id=3)

-- Note: In production, use proper password hashing (e.g., password_hash() in PHP)
-- Also, adjust file paths and permissions as needed.