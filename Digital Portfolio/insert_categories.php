<?php
require 'db.php';

// Clear existing categories to avoid duplicates
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM categories");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Insert categories in order
$year1 = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$year1->execute(['Year 1: IT Fundamentals - NHL Stenden (2025-2026)', null]);
$year1_id = $pdo->lastInsertId();

$sem1 = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$sem1->execute(['Semester 1: Foundation Modules (Periods 1 & 2)', $year1_id]);
$sem1_id = $pdo->lastInsertId();

$period1 = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$period1->execute(['Period 1: Web Development Basics (HTML, CSS, PHP, Figma)', $sem1_id]);

$period1_prof = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$period1_prof->execute(['Period 1 Professional Skills & Documentation', $sem1_id]);

$period2 = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$period2->execute(['Period 2: Database Management (MySQL, SQL, Proxmox)', $sem1_id]);

$period2_prof = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$period2_prof->execute(['Period 2 Professional Skills & Documentation', $sem1_id]);

$sem2 = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$sem2->execute(['Semester 2: Future Modules (Periods 3 & 4)', $year1_id]);
$sem2_id = $pdo->lastInsertId();

$tools = $pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
$tools->execute(['Tools & Version Control (GitHub, Docker, VS Code)', $year1_id]);

echo 'Categories reset and inserted.';
?>