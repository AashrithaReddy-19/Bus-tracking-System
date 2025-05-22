<?php
include "config.php";
if (!$conn) {
    die(json_encode(["success" => false, "error" => "Database connection failed"]));
}

$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : null;
if ($bus_id) {
    // Fetch current location
    $sql = "SELECT latitude, longitude FROM buses WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentLat = floatval($row['latitude']);
        $currentLng = floatval($row['longitude']);

        // Simulate movement (increment by a small random amount)
        $latChange = rand(-100, 100) / 1000000; // Small random change (e.g., 0.000001 to 0.0001 degrees)
        $lngChange = rand(-100, 100) / 1000000;
        $newLat = $currentLat + $latChange;
        $newLng = $currentLng + $lngChange;

        // Update the database with new coordinates (for persistence)
        $updateSql = "UPDATE buses SET latitude = ?, longitude = ?, last_updated = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ddi", $newLat, $newLng, $bus_id);
        $updateStmt->execute();
        $updateStmt->close();

        echo json_encode(["success" => true, "latitude" => $newLat, "longitude" => $newLng]);
    } else {
        echo json_encode(["success" => false, "error" => "Bus not found"]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "No bus ID provided"]);
}
?>