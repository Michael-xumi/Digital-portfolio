<?php
require 'db.php';

echo "<h1>Moving Week Assignments to Web Development</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .info { color: blue; }
</style>";

try {
    // Get the correct category IDs
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    
    // Period 1: Web Development Basics
    $stmt->execute(['Period 1: Web Development Basics (HTML, CSS, PHP, Figma)']);
    $period1_web = $stmt->fetch()['id'];
    
    echo "<p class='info'>Period 1 Web Development ID: {$period1_web}</p>";
    echo "<hr>";
    
    // Move all "Week" assignments to Web Development
    echo "<h2>Moving Week Assignments</h2>";
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE title LIKE '%Week%'");
    $stmt->execute([$period1_web]);
    $count = $stmt->rowCount();
    echo "<p class='success'>✓ Moved {$count} Week assignments to Period 1 Web Development</p>";
    
    // Show what's in each category now
    echo "<hr>";
    echo "<h2>Current Category Distribution</h2>";
    
    $stmt = $pdo->query("
        SELECT c.name, COUNT(f.id) as file_count, GROUP_CONCAT(f.title SEPARATOR ', ') as files
        FROM categories c 
        LEFT JOIN files f ON c.id = f.category_id 
        WHERE f.status = 'active'
        GROUP BY c.id, c.name
        HAVING file_count > 0
        ORDER BY c.id
    ");
    $summary = $stmt->fetchAll();
    
    foreach ($summary as $row) {
        echo "<div style='margin: 20px 0; padding: 15px; background: #f0f0f0; border-left: 4px solid #06b6d4;'>";
        echo "<h3 style='margin-top: 0;'>{$row['name']} ({$row['file_count']} files)</h3>";
        $files = explode(', ', $row['files']);
        echo "<ul style='margin: 0;'>";
        foreach (array_slice($files, 0, 10) as $file) {
            echo "<li>" . htmlspecialchars($file) . "</li>";
        }
        if (count($files) > 10) {
            echo "<li><em>... and " . (count($files) - 10) . " more</em></li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<p><a href='portfolio.php' style='background: #06b6d4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>🎯 Go to Portfolio Page</a></p>";
    echo "<p><a href='debug_files.php'>View Debug Info</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
