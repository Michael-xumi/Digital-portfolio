
<?php
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digit = (int)$_POST['digit'];
    switch ($digit) {
        case 1:
        case 2:
        case 3:
            $message = "Very bad";
            break;
        case 4:
        case 5:
            $message = "Insufficient";
            break;
        case 6:
        case 7:
            $message = "Sufficient";
            break;
        case 8:
            $message = "Good";
            break;
        case 9:
            $message = "Very good";
            break;
        case 10:
            $message = "Excellent";
            break;
        default:
            $message = "Invalid figure";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Grade Calculator (Switch)</title>
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
