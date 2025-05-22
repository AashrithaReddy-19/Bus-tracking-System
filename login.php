<?php
session_start();
include "config.php";

// Logout mechanism to reset session
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle login logic
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $sap_id = $_POST['sap_id'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE sap_id='$sap_id'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if ($password == $user['password']) {  // Use password_hash() in real apps
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['authenticated'] = true;
        } else {
            echo "<script>alert('❌ Invalid Password');</script>";
        }
    } else {
        echo "<script>alert('❌ User Not Found');</script>";
    }
}

// Handle role selection
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['role'])) {
    if ($_POST['role'] == "admin") {
        header("Location: admin.php");
        exit();
    } elseif ($_POST['role'] == "student") {
        header("Location: track_bus.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, #e6f0fa, #f0f8ff); /* Light blue gradient matching the image */
            margin: 0;
            padding: 50px;
            text-align: center;
            color: #333;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            display: inline-block;
            max-width: 400px; /* Added to control width similar to the table container */
        }
        h3 {
            color: #0056b3; /* Matching the header text color */
            font-size: 24px; /* Slightly larger for emphasis */
            margin-bottom: 20px;
        }
        input {
            width: 100%; /* Full width within container */
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box; /* Ensure padding doesn't affect width */
        }
        button {
            background-color: #0056b3; /* Matching the header and button color */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
            width: 100%; /* Full width buttons */
            margin: 5px 0;
        }
        button:hover {
            background-color: #003d82; /* Darker shade on hover */
        }
        .logout-btn {
            background-color: #dc3545; /* Red color adjusted to match logout theme */
            width: 100%;
        }
        .logout-btn:hover {
            background-color: #a71d2a; /* Darker red on hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['authenticated'])): ?>
            <h3>Login</h3>
            <form method="POST">
                <input type="text" name="sap_id" placeholder="SAP ID" required><br>
                <input type="password" name="password" placeholder="Password" required><br>
                <button type="submit" name="login">Login</button>
            </form>
        <?php else: ?>
            <h3>Welcome! Select Your Role:</h3>
            <form method="POST">
                <button type="submit" name="role" value="admin">Admin</button>
                <button type="submit" name="role" value="student">Student</button>
            </form>
            <br>
            <a href="login.php?logout=true"><button class="logout-btn">Logout</button></a>
        <?php endif; ?>
    </div>
</body>
</html>