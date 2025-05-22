<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied!");
}

include "config.php"; // Assuming this contains your database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Handle status/location update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_bus'])) {
    if (isset($_POST['bus_id'], $_POST['status'], $_POST['location'], $_POST['latitude'], $_POST['longitude'])) {
        $bus_id = intval($_POST['bus_id']);
        $new_status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');
        $new_location = htmlspecialchars($_POST['location'], ENT_QUOTES, 'UTF-8');
        $latitude = floatval($_POST['latitude']);
        $longitude = floatval($_POST['longitude']);
        $live_tracking = isset($_POST['live_tracking']) ? 1 : 0;

        if (!empty($_POST['google_maps_link'])) {
            preg_match('/@([-0-9.]+),([-0-9.]+),?/', $_POST['google_maps_link'], $matches);
            if (isset($matches[1], $matches[2])) {
                $latitude = floatval($matches[1]);
                $longitude = floatval($matches[2]);
            }
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            error_log("Invalid latitude/longitude for bus $bus_id: lat=$latitude, lon=$longitude");
            $response = ['success' => false, 'error' => 'Invalid latitude or longitude values'];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $sql = "UPDATE buses SET status=?, location=?, latitude=?, longitude=?, live_tracking=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssddi", $new_status, $new_location, $latitude, $longitude, $live_tracking, $bus_id);
            if ($stmt->execute()) {
                error_log("Bus $bus_id updated: status=$new_status, location=$new_location, lat=$latitude, lon=$longitude");
                $log_sql = "INSERT INTO bus_status_logs (bus_id, status, location, timestamp) VALUES (?, ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $bus_id, $new_status, $new_location);
                    if (!$log_stmt->execute()) {
                        error_log("Failed to log status for bus $bus_id: " . $log_stmt->error);
                    }
                    $log_stmt->close();
                }
                $response = ['success' => true, 'message' => 'Bus location updated!', 'bus_id' => $bus_id];
                header('Content-Type: application/json');
                echo json_encode($response);
            } else {
                error_log("Update failed for bus $bus_id: " . $stmt->error);
                $response = ['success' => false, 'error' => $stmt->error];
                header('Content-Type: application/json');
                echo json_encode($response);
            }
            $stmt->close();
        }
        exit;
    }
}

// Handle start live tracking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_live_tracking'])) {
    $bus_id = intval($_POST['bus_id']);
    $live_duration = isset($_POST['live_duration']) ? intval($_POST['live_duration']) : 3600; // Default 1 hour in seconds
    $sql = "UPDATE buses SET live_tracking = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bus_id);
        if ($stmt->execute()) {
            // Generate a unique shareable link (simple implementation)
            $share_link = "track_bus.php?bus_id={$bus_id}&live=true&expires=" . (time() + $live_duration);
            $response = ['success' => true, 'message' => 'Live tracking enabled!', 'share_link' => $share_link];
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $response = ['success' => false, 'error' => 'Failed to enable live tracking'];
            header('Content-Type: application/json');
            echo json_encode($response);
        }
        $stmt->close();
        exit;
    }
}

// Handle stop live tracking
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['stop_live_tracking'])) {
    $bus_id = intval($_POST['bus_id']);
    $sql = "UPDATE buses SET live_tracking = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bus_id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Live tracking stopped!'];
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            $response = ['success' => false, 'error' => 'Failed to stop live tracking'];
            header('Content-Type: application/json');
            echo json_encode($response);
        }
        $stmt->close();
        exit;
    }
}

// Fetch buses and status count
$sql = "SELECT id, bus_number, status, latitude, longitude, live_tracking FROM buses";
$result = $conn->query($sql);
$buses = [];
$status_count = ["Running" => 0, "Stopped" => 0, "Refueling" => 0];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
        $status_count[$row['status']] = ($status_count[$row['status']] ?? 0) + 1;
    }
} else {
    error_log("No buses found in the database: " . $conn->error);
}

// Filter logs
$where = "1=1";
if (!empty($_GET['bus_number'])) {
    $bus_no = $conn->real_escape_string($_GET['bus_number']);
    $where .= " AND b.bus_number = '$bus_no'";
}
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $from = $conn->real_escape_string($_GET['start_date']);
    $to = $conn->real_escape_string($_GET['end_date']);
    $where .= " AND DATE(l.timestamp) BETWEEN '$from' AND '$to'";
}

$logs_result = $conn->query("SELECT b.bus_number, l.status, l.location, l.timestamp 
    FROM bus_status_logs l 
    JOIN buses b ON b.id = l.bus_id 
    WHERE $where 
    ORDER BY l.timestamp DESC 
    LIMIT 50");
$logs = [];
if ($logs_result && $logs_result->num_rows > 0) {
    while ($row = $logs_result->fetch_assoc()) {
        $logs[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Smart Bus Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC1iqSD_5VLBXSCAU9BEhE6QwdZ2dLIr2Y&libraries=places"></script>
    <style>
        :root {
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .navbar {
            background: var(--primary);
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar .logo {
            color: white;
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .navbar .nav-links {
            display: flex;
            gap: 20px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            cursor: pointer;
        }

        .navbar a:hover, .navbar a.active {
            color: var(--light);
        }

        .container {
            max-width: 1200px;
            margin: 80px auto 40px;
            padding: 0 20px;
        }

        h2, h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            padding: 20px;
            display: none;
        }

        .card.active {
            display: block;
            opacity: 1;
        }

        .card:hover {
            transform: translateY(-5px);
            transition: transform 0.3s ease;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--primary);
            color: white;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }

        .Running { background: var(--success); }
        .Stopped { background: var(--danger); }
        .Refueling { background: var(--warning); color: var(--dark); }

        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border-radius: 10px;
            border: 1px solid var(--secondary);
            font-size: 14px;
        }

        button {
            padding: 12px 20px;
            margin: 5px;
            border: none;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            cursor: pointer;
            font-weight: 500;
        }

        button:hover {
            background: #0056b3;
        }

        .live-btn {
            background: var(--success);
        }

        .live-btn:hover {
            background: #218838;
        }

        .chart-container {
            position: relative;
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-height: 400px;
            overflow: hidden;
        }

        canvas {
            max-width: 500px;
            width: 100%;
            max-height: 300px;
            margin: 0 auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="nav-container">
            <div class="logo">ADMIN BUS TRACK</div>
            <div class="nav-links">
                <a data-section="overview" class="<?php echo isset($_GET['section']) && $_GET['section'] == 'overview' ? 'active' : ''; ?>">Overview</a>
                <a data-section="manage" class="<?php echo isset($_GET['section']) && $_GET['section'] == 'manage' ? 'active' : ''; ?>">Manage Buses</a>
                <a data-section="logs" class="<?php echo isset($_GET['section']) && $_GET['section'] == 'logs' ? 'active' : ''; ?>">Status Logs</a>
                <a data-section="maps" class="<?php echo isset($_GET['section']) && $_GET['section'] == 'maps' ? 'active' : ''; ?>">Map View</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="card" id="overview">
            <h2>🛠 Admin Overview</h2>
            <div class="chart-container">
                <h3>📈 Bus Status Overview</h3>
                <?php if (empty($buses)): ?>
                    <p>No bus data available.</p>
                <?php else: ?>
                    <canvas id="statusChart"></canvas>
                <?php endif; ?>
            </div>
            <div class="stats" id="status-stats">
                <div>🟢 Running: <span id="running-count"><?= $status_count["Running"] ?></span></div>
                <div>🔴 Stopped: <span id="stopped-count"><?= $status_count["Stopped"] ?></span></div>
                <div>🟡 Refueling: <span id="refueling-count"><?= $status_count["Refueling"] ?></span></div>
            </div>
        </div>

        <div class="card" id="manage">
            <h2>✏ Manage Bus Status & Location</h2>
            <form method="POST" id="locationForm">
                <input type="hidden" name="update_bus" value="1">
                <select name="bus_id" id="bus_id" required>
                    <option value="">--Select Bus--</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?= $bus['id'] ?>"><?= htmlspecialchars($bus['bus_number']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" id="status" required>
                    <option value="Running">Running</option>
                    <option value="Stopped">Stopped</option>
                    <option value="Refueling">Refueling</option>
                </select>
                <input type="text" name="location" id="location" placeholder="Enter location" required>
                <input type="text" name="google_maps_link" id="google_maps_link" placeholder="Paste Google Maps URL">
                <input type="text" id="latitude" name="latitude" placeholder="Latitude" required>
                <input type="text" id="longitude" name="longitude" placeholder="Longitude" required>
                <label><input type="checkbox" name="live_tracking" id="live_tracking"> Enable Live Tracking</label>
                <br>
                <button type="button" class="live-btn" onclick="getLiveLocation()">📍 Use Live Location</button>
                <button type="submit">Update 🚍</button>
            </form>
        </div>

        <div class="card" id="logs">
            <h2>📅 Status Logs</h2>
            <form method="GET">
                <select name="bus_number">
                    <option value="">All Buses</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?= htmlspecialchars($bus['bus_number']) ?>" <?= isset($_GET['bus_number']) && $_GET['bus_number'] == $bus['bus_number'] ? 'selected' : '' ?>><?= htmlspecialchars($bus['bus_number']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
                <input type="date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
                <button type="submit">Filter 🔍</button>
            </form>
            <table>
                <thead>
                    <tr><th>Bus</th><th>Status</th><th>Location</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['bus_number']) ?></td>
                            <td><span class="badge <?= htmlspecialchars($log['status']) ?>"><?= htmlspecialchars($log['status']) ?></td>
                            <td><?= htmlspecialchars($log['location']) ?></td>
                            <td><?= date('d M Y, H:i', strtotime($log['timestamp'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card" id="maps">
            <h2>🌍 Map View</h2>
            <?php foreach ($buses as $bus): ?>
                <p>
                    🚍 <?= htmlspecialchars($bus['bus_number']) ?> → 
                    <a href="https://www.google.com/maps?q=<?= $bus['latitude'] ?>,<?= $bus['longitude'] ?>" target="_blank">View on Map</a> 
                    (<?= htmlspecialchars($bus['status']) ?>) 
                    | <a href="#" onclick="shareLiveLocation(<?= $bus['id'] ?>); return false;">Share Live Location</a>
                </p>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('.navbar a');
            const cards = document.querySelectorAll('.card');
            let chartInstance = null;

            function showSection(sectionId) {
                cards.forEach(card => {
                    card.classList.toggle('active', card.id === sectionId);
                });
                navLinks.forEach(link => {
                    link.classList.toggle('active', link.getAttribute('data-section') === sectionId);
                });
                history.pushState({}, '', '?section=' + sectionId);
            }

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (!link.href.includes('logout.php')) {
                        e.preventDefault();
                        showSection(link.getAttribute('data-section'));
                    }
                });
            });

            const urlParams = new URLSearchParams(window.location.search);
            showSection(urlParams.get('section') || 'overview');

            // Initialize Chart
            const ctx = document.getElementById('statusChart')?.getContext('2d');
            if (ctx) {
                chartInstance = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Running', 'Stopped', 'Refueling'],
                        datasets: [{
                            label: 'Bus Status',
                            data: [<?= $status_count["Running"] ?>, <?= $status_count["Stopped"] ?>, <?= $status_count["Refueling"] ?>],
                            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            title: {
                                display: true,
                                text: 'Bus Status Distribution',
                                font: { size: 18 }
                            }
                        }
                    }
                });
            }

            // Form submission with AJAX
            const locationForm = document.getElementById('locationForm');
            if (locationForm) {
                locationForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    fetch('admin.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✅ ' + data.message);
                            updateOverview(() => {
                                window.location.reload();
                            });
                        } else {
                            alert('❌ Error updating location: ' + data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('❌ An unexpected error occurred');
                    });
                });
            }

            // Update Overview
            window.updateOverview = function(callback) {
                fetch('admin.php?fetch_status=true', { method: 'GET' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('running-count').textContent = data.status_count.Running;
                            document.getElementById('stopped-count').textContent = data.status_count.Stopped;
                            document.getElementById('refueling-count').textContent = data.status_count.Refueling;
                            if (chartInstance) {
                                chartInstance.data.datasets[0].data = [
                                    data.status_count.Running,
                                    data.status_count.Stopped,
                                    data.status_count.Refueling
                                ];
                                chartInstance.update();
                            }
                            if (typeof callback === 'function') callback();
                        }
                    })
                    .catch(error => console.error('Fetch error:', error));
            };

            // Live Location
            window.getLiveLocation = function() {
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            latitudeInput.value = position.coords.latitude.toFixed(6);
                            longitudeInput.value = position.coords.longitude.toFixed(6);
                            alert('Live Location Updated!\nLatitude: ' + latitudeInput.value + '\nLongitude: ' + longitudeInput.value);
                        },
                        (error) => {
                            alert('Geolocation Error: ' + error.message);
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                } else {
                    alert('Geolocation is not supported by your browser.');
                }
            };

            // Share Live Location
            window.shareLiveLocation = function(busId) {
                const formData = new FormData();
                formData.append('bus_id', busId);
                formData.append('start_live_tracking', 1);
                formData.append('live_duration', 3600); // 1 hour duration

                fetch('admin.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const shareLink = data.share_link;
                        alert('Live tracking enabled! Share this link: ' + shareLink);
                        window.open(shareLink, '_blank'); // Open live view in new tab
                    } else {
                        alert('Error enabling live tracking: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error enabling live tracking: ' + error.message);
                });
            };
        });

        <?php
        // Handle AJAX request for updated status count
        if (isset($_GET['fetch_status']) && $_GET['fetch_status'] === 'true') {
            $sql = "SELECT status FROM buses";
            $result = $conn->query($sql);
            $status_count = ["Running" => 0, "Stopped" => 0, "Refueling" => 0];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $status_count[$row['status']] = ($status_count[$row['status']] ?? 0) + 1;
                }
            }
            header('Content-Type: application/json');
            echo json_encode(["success" => true, "status_count" => $status_count]);
            exit;
        }
        ?>
    </script>
</body>
</html>