<?php
// db.php - Database connection script
// Update these credentials to match your MySQL setup
$host = 'mysql'; // Docker service name for MySQL
$dbname = 'portfolio_db'; // Database name
$username = 'root'; // MySQL username
$password = 'qwerty'; // MySQL password from .env

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>