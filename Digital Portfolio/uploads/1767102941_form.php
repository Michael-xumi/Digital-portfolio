<?php




?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../trials/form.css">
    <title>Webinar Subscription</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }
        button {
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Webinar Subscription</h2>
        <form action="form.php" method="POST">

            <label for="fname">First Name</label>
            <input type="text" name="fname" id="fname">

            <label for="lname">Last Name</label>
            <input type="text" name="lname" id="lname">

            <label for="email">Email</label>
            <input type="email" name="email" id="email">

            <label for="phone">Workphone</label>
            <input type="tel" name="phone" id="phone" placeholder="+31 6 12345678">

            <label for="company">Company</label>
            <input type="text" name="company" id="company">

            <label for="street">Company Address</label>
            <input type="text" name="street" id="street" placeholder="Street Address">
            <input type="text" name="street2" id="street2" placeholder="Street Address line 2">
            <input type="text" name="city" id="city" placeholder="City">
            <input type="text" name="state" id="state" placeholder="State / Province">
            <input type="text" name="zip" id="zip" placeholder="Postal / ZIP Code">

            <label for="cwebsite">Company Website</label>
            <input type="url" name="cwebsite" id="cwebsite" placeholder="https://example.com">

            <button type="submit">Submit</button>

        </form>
    </div>

</body>

</html>