<?php
include "config.php";
$logs = [];
$logs_result = $conn->query("SELECT b.bus_number, l.status, l.location, l.timestamp 
    FROM bus_status_logs l 
    JOIN buses b ON b.id = l.bus_id 
    ORDER BY l.timestamp DESC 
    LIMIT 100");
if ($logs_result) {
    while ($row = $logs_result->fetch_assoc()) {
        $logs[] = $row;
    }
}
header('Content-Type: application/json');
echo json_encode($logs);
?>
