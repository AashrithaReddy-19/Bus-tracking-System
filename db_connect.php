<?php
$servername = "localhost";  // XAMPP runs MySQL on localhost
$username = "root";         // Default MySQL username in XAMPP
$password = "";             // No password by default in XAMPP
$dbname = "bus_tracking";   // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database Connected Successfully!";
}
?>
