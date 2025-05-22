<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    die("Access Denied!");
}
include "config.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
</head>
<body>
    <h2>Welcome Student</h2>
    <h3>Click on a Bus to View Details & Location</h3>

    <table border="1">
        <tr>
            <th>Bus Number</th>
            <th>Driver Name</th>
            <th>Departure Time</th>
            <th>Status</th>
            <th>View Details</th>
        </tr>

        <?php
        $sql = "SELECT * FROM buses";
        $result = $conn->query($sql);

        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['bus_number']}</td>
                <td>{$row['driver_name']}</td>
                <td>{$row['departure_time']}</td>
                <td>{$row['status']}</td>
                <td><a href='track_bus.php?id={$row['id']}'>Track Bus</a></td>
            </tr>";
        }
        ?>
    </table>

    <a href="logout.php">Logout</a>
</body>
</html>