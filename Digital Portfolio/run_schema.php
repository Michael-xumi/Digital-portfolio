<?php
require 'db.php';

$sql = file_get_contents('schema.sql');

try {
    $pdo->exec($sql);
    echo 'Schema executed successfully.';
} catch (PDOException $e) {
    echo 'Error executing schema: ' . $e->getMessage();
}
?>