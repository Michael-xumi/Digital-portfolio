<?php
// debug_files.php - Check what's in your database
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

echo "<h1>Database Debug Information</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .section { margin: 30px 0; }
    .warning { color: red; font-weight: bold; }
    .success { color: green; font-weight: bold; }
</style>";

// 1. Check all files
echo "<div class='section'>";
echo "<h2>All Files in Database</h2>";
$stmt = $pdo->query("SELECT f.*, c.name as category_name, u.username as uploader 
                      FROM files f 
                      LEFT JOIN categories c ON f.category_id = c.id 
                      LEFT JOIN users u ON f.uploaded_by = u.id 
                      ORDER BY f.id DESC");
$files = $stmt->fetchAll();

if (empty($files)) {
    echo "<p class='warning'>NO FILES FOUND IN DATABASE!</p>";
} else {
    echo "<p class='success'>Found " . count($files) . " files</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Title</th><th>Category ID</th><th>Category Name</th><th>Status</th><th>Uploader</th><th>File Path</th></tr>";
    foreach ($files as $file) {
        $category_warning = !$file['category_id'] ? " class='warning'" : "";
        echo "<tr>";
        echo "<td>{$file['id']}</td>";
        echo "<td>{$file['title']}</td>";
        echo "<td{$category_warning}>" . ($file['category_id'] ?? 'NULL') . "</td>";
        echo "<td{$category_warning}>" . ($file['category_name'] ?? 'NO CATEGORY') . "</td>";
        echo "<td>{$file['status']}</td>";
        echo "<td>{$file['uploader']}</td>";
        echo "<td>{$file['file_path']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 2. Check all categories
echo "<div class='section'>";
echo "<h2>All Categories</h2>";
$stmt = $pdo->query("SELECT c.*, p.name as parent_name 
                      FROM categories c 
                      LEFT JOIN categories p ON c.parent_id = p.id 
                      ORDER BY c.id");
$categories = $stmt->fetchAll();

if (empty($categories)) {
    echo "<p class='warning'>NO CATEGORIES FOUND!</p>";
} else {
    echo "<p class='success'>Found " . count($categories) . " categories</p>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Parent ID</th><th>Parent Name</th></tr>";
    foreach ($categories as $cat) {
        echo "<tr>";
        echo "<td>{$cat['id']}</td>";
        echo "<td>{$cat['name']}</td>";
        echo "<td>" . ($cat['parent_id'] ?? 'NULL (TOP LEVEL)') . "</td>";
        echo "<td>" . ($cat['parent_name'] ?? '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 3. Check permissions
echo "<div class='section'>";
echo "<h2>File Permissions</h2>";
$stmt = $pdo->query("SELECT p.*, u.username, f.title 
                      FROM permissions p 
                      LEFT JOIN users u ON p.user_id = u.id 
                      LEFT JOIN files f ON p.file_id = f.id");
$permissions = $stmt->fetchAll();

if (empty($permissions)) {
    echo "<p class='warning'>NO PERMISSIONS SET!</p>";
} else {
    echo "<p class='success'>Found " . count($permissions) . " permissions</p>";
    echo "<table>";
    echo "<tr><th>User</th><th>File Title</th><th>Granted At</th></tr>";
    foreach ($permissions as $perm) {
        echo "<tr>";
        echo "<td>{$perm['username']}</td>";
        echo "<td>{$perm['title']}</td>";
        echo "<td>{$perm['granted_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
echo "</div>";

// 4. Check current user's role and accessible files
echo "<div class='section'>";
echo "<h2>Your Access Information</h2>";
echo "<p><strong>Username:</strong> {$_SESSION['username']}</p>";
echo "<p><strong>Role:</strong> {$_SESSION['role']}</p>";
echo "<p><strong>User ID:</strong> {$_SESSION['user_id']}</p>";

if ($_SESSION['role'] === 'Administrator') {
    echo "<p class='success'>As an Administrator, you should see ALL active files.</p>";
} else {
    echo "<p>As a Visitor, you can only see files with permissions.</p>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM permissions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetch()['count'];
    echo "<p class='success'>You have access to {$count} files.</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>Quick Fixes</h2>";
echo "<ul>";
echo "<li><a href='insert_categories.php'>Reset and Insert Categories</a></li>";
echo "<li><a href='fix_orphaned.php'>Fix Orphaned Files (assign category)</a></li>";
echo "<li><a href='portfolio.php'>Go to Portfolio Page</a></li>";
echo "<li><a href='admin_upload.php'>Go to Upload Page</a></li>";
echo "</ul>";
echo "</div>";
?>