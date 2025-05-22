<?php
include "config.php";

$sql = "SELECT * FROM buses WHERE status='Running'";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "🚍 Bus " . $row['bus_number'] . " has started!<br>";
}

$sql_refuel = "SELECT * FROM buses WHERE status='Refueling'";
$result_refuel = $conn->query($sql_refuel);

while ($row = $result_refuel->fetch_assoc()) {
    echo "⛽ Bus " . $row['bus_number'] . " is refueling!<br>";
}
?>
