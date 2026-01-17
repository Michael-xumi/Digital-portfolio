
<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digit = (int)$_POST['digit'];
    if ($digit < 1 || $digit > 10) {
        $message = "Invalid figure";
    } elseif ($digit >= 1 && $digit <= 3) {
        $message = "Very bad";
    } elseif ($digit == 4 || $digit == 5) {
        $message = "Insufficient";
    } elseif ($digit == 6 || $digit == 7) {
        $message = "Sufficient";
    } elseif ($digit == 8) {
        $message = "Good";
    } elseif ($digit == 9) {
        $message = "Very good";
    } elseif ($digit == 10) {
        $message = "Excellent";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grade Calculator</title>
</head>
<body>
    <h1>Enter a digit from 1 to 10</h1>
    <form method="post">
        <input type="number" name="digit" min="1" max="10" required>
        <button type="submit">Submit</button>
    </form>
    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>
</body>
</html>