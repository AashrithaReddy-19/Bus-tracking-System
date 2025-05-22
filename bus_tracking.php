<?php
session_start();
include "config.php";

// Fetch all buses
$sql = "SELECT id, bus_number, location, status FROM buses";
$result = $conn->query($sql);

if (!$result) {
    die("Error fetching buses: " . $conn->error);
}

// Check if a bus ID is selected
$bus = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bus_id = intval($_GET['id']);

    // Fetch details of selected bus
    $stmt = $conn->prepare("SELECT * FROM buses WHERE id = ?");
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $busResult = $stmt->get_result();
    
    if ($busResult->num_rows > 0) {
        $bus = $busResult->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to right, #4facfe, #00f2fe);
            margin: 0;
            padding: 20px;
            text-align: center;
            color: #333;
        }
        h2 {
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            font-size: 18px;
        }
        tr:hover {
            background-color: #f1f1f1;
            transition: 0.3s;
        }
        a {
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            transition: 0.3s;
        }
        a:hover {
            color: #ff4500;
            transform: scale(1.1);
        }
        .warning {
            color: red;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Select a Bus to Track</h2>
    <table>
        <thead>
            <tr>
                <th>Bus Number</th>
                <th>Location</th>
                <th>Status</th>
                <th>Track</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['bus_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><a href="bus_tracking.php?id=<?php echo $row['id']; ?>">View Details</a></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if ($bus): ?>
        <h2>Bus Details</h2>
        <table>
            <thead>
                <tr>
                    <th>Detail</th>
                    <th>Information</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>Bus Number</b></td>
                    <td><?php echo htmlspecialchars($bus['bus_number']); ?></td>
                </tr>
                <tr>
                    <td><b>Driver Name</b></td>
                    <td><?php echo htmlspecialchars($bus['driver_name']); ?></td>
                </tr>
                <tr>
                    <td><b>Driver Contact</b></td>
                    <td><?php echo htmlspecialchars($bus['driver_contact']); ?></td>
                </tr>
                <tr>
                    <td><b>Current Status</b></td>
                    <td><?php echo htmlspecialchars($bus['status']); ?></td>
                </tr>
                <tr>
                    <td><b>Last Updated Location</b></td>
                    <td><?php echo htmlspecialchars($bus['location']); ?></td>
                </tr>
            </tbody>
        </table>

        <h3>Live Location:</h3>
        <?php if (!empty($bus['latitude']) && !empty($bus['longitude'])): ?>
            <iframe 
                loading="lazy" 
                allowfullscreen 
                referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps/embed/v1/place?key=YOUR_GOOGLE_MAPS_API_KEY&q=<?php echo urlencode($bus['latitude'] . ',' . $bus['longitude']); ?>">
            </iframe>
        <?php else: ?>
            <p class="warning">⚠️ Location not updated by admin yet.</p>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
