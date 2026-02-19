<?php
require 'db.php';

echo "<h1>Fixing File Categories</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .info { color: blue; }
    .warning { color: orange; }
</style>";

try {
    // Get the correct category IDs from the current database
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    
    // Period 1: Web Development Basics
    $stmt->execute(['Period 1: Web Development Basics (HTML, CSS, PHP, Figma)']);
    $period1_web = $stmt->fetch()['id'];
    
    // Period 1: Professional Skills
    $stmt->execute(['Period 1 Professional Skills & Documentation']);
    $period1_prof = $stmt->fetch()['id'];
    
    // Period 2: Database Management
    $stmt->execute(['Period 2: Database Management (MySQL, SQL, Proxmox)']);
    $period2_db = $stmt->fetch()['id'];
    
    echo "<p class='info'>Period 1 Web Development ID: {$period1_web}</p>";
    echo "<p class='info'>Period 1 Professional Skills ID: {$period1_prof}</p>";
    echo "<p class='info'>Period 2 Database Management ID: {$period2_db}</p>";
    echo "<hr>";
    
    // 1. Assign Period 2 files (Database-related files)
    echo "<h2>Step 1: Assigning Period 2 Database Files</h2>";
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE title LIKE ? OR title LIKE ? OR title LIKE ? OR title LIKE ? OR title LIKE ?");
    $stmt->execute([
        $period2_db,
        '%Session%',
        '%Login%',
        '%Fileupload%',
        '%interaction%',
        '%Arrays%'
    ]);
    $count1 = $stmt->rowCount();
    echo "<p class='success'>✓ Assigned {$count1} database-related files to Period 2 Database Management</p>";
    
    // 2. Assign Professional Skills files
    echo "<h2>Step 2: Assigning Professional Skills Files</h2>";
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE title LIKE ? OR title LIKE ? OR title LIKE ? OR title LIKE ?");
    $stmt->execute([
        $period1_prof,
        '%Assignment%',
        '%planning%',
        '%Minutes%',
        '%Feedback%'
    ]);
    $count2 = $stmt->rowCount();
    echo "<p class='success'>✓ Assigned {$count2} files to Period 1 Professional Skills</p>";
    
    // 3. Assign all remaining files (Week assignments, Examples) to Period 1 Web Development
    echo "<h2>Step 3: Assigning Week Assignments and Examples</h2>";
    $stmt = $pdo->prepare("UPDATE files SET category_id = ? WHERE category_id IN (136, 137, 138) OR category_id IS NULL");
    $stmt->execute([$period1_web]);
    $count3 = $stmt->rowCount();
    echo "<p class='success'>✓ Assigned {$count3} remaining files to Period 1 Web Development</p>";
    
    echo "<hr>";
    echo "<p class='success' style='font-size: 1.2em;'>🎉 Total Fixed: " . ($count1 + $count2 + $count3) . " files</p>";
    
    // Show summary of what was assigned where
    echo "<h2>Summary by Category</h2>";
    $stmt = $pdo->query("
        SELECT c.name, COUNT(f.id) as file_count 
        FROM categories c 
        LEFT JOIN files f ON c.id = f.category_id 
        WHERE f.status = 'active'
        GROUP BY c.id, c.name
        HAVING file_count > 0
        ORDER BY c.id
    ");
    $summary = $stmt->fetchAll();
    
    echo "<ul>";
    foreach ($summary as $row) {
        echo "<li><strong>{$row['name']}</strong>: {$row['file_count']} files</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<p><a href='portfolio.php' style='background: #06b6d4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>🎯 Go to Portfolio Page to See Your Files!</a></p>";
    echo "<p><a href='debug_files.php'>View Debug Info Again</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
