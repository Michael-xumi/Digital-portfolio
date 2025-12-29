<?php
// logout.php - Logout script
session_start();
session_destroy();
header('Location: login.php');
exit;
?>