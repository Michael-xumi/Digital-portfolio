<?php
require 'db.php';

echo "<h1>Fixing Orphaned Files</h1>";

try {
    // Get category IDs
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute(['Period 1: Web Development Basics (HTML, CSS, PHP, Figma)']);
    $period1_web = $stmt->fetch()['id'];
    
    $stmt->execute(['Period 1 Professional Skills & Documentation']);
    $period1_prof = $stmt->fetch()['id'];
    
    echo "<p>Period 1 Web Dev ID: $period1_web</p>";
    echo "<p>Period 1 Prof Skills ID: $period1_prof</p>";
    
    // Assign files with "Week" or "Example" to Period 1 Web Dev
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE (title LIKE ? OR title LIKE ? OR title LIKE ?) AND category_id IS NULL");
    $stmt->execute([$period1_web, '%Week%', '%Example%', '%grid%']);
    $count1 = $stmt->rowCount();
    echo "<p>✓ Assigned $count1 files to Period 1 Web Development</p>";
    
    // Assign remaining files with "Assignment" or "planning" to Period 1 Prof Skills
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE (title LIKE ? OR title LIKE ?) AND category_id IS NULL");
    $stmt->execute([$period1_prof, '%Assignment%', '%planning%']);
    $count2 = $stmt->rowCount();
    echo "<p>✓ Assigned $count2 files to Period 1 Professional Skills</p>";
    
    // Assign any remaining orphaned files to Period 1 Web Dev
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE category_id IS NULL");
    $stmt->execute([$period1_web]);
    $count3 = $stmt->rowCount();
    echo "<p>✓ Assigned $count3 remaining files to Period 1 Web Development</p>";
    
    echo "<p style='color: green; font-weight: bold;'>✓ Total Fixed: " . ($count1 + $count2 + $count3) . " files</p>";
    echo "<p><a href='portfolio.php'>Go to Portfolio</a> to see your files now!</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
