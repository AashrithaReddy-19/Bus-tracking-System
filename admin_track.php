<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied!");
}

include "config.php";
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

        $sql = "UPDATE buses SET status=?, location=?, latitude=?, longitude=?, live_tracking=?, last_updated=NOW() WHERE id=?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssddi", $new_status, $new_location, $latitude, $longitude, $live_tracking, $bus_id);
            if ($stmt->execute()) {
                $log_sql = "INSERT INTO bus_status_logs (bus_id, status, location, timestamp) VALUES (?, ?, ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $log_stmt->bind_param("iss", $bus_id, $new_status, $new_location);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                echo "<script>alert('✅ Bus location updated!'); window.location.href='admin.php?section=manage';</script>";
            } else {
                echo "<script>alert('❌ Error updating location: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        }
    }
}

// Handle starting live tracking and sharing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['start_live_tracking'])) {
    $bus_id = intval($_POST['bus_id']);
    $sql = "UPDATE buses SET live_tracking = 1, last_updated = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bus_id);
        if ($stmt->execute()) {
            echo "<script>alert('✅ Live tracking started for bus ID: $bus_id. Share this link: http://localhost/track_bus.php?bus_id=$bus_id'); window.location.href='admin.php?section=manage';</script>";
        } else {
            echo "<script>alert('❌ Error starting live tracking: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    }
}

// Fetch buses and status count
$sql = "SELECT id, bus_number, status, latitude, longitude, live_tracking, last_updated FROM buses";
$result = $conn->query($sql);
$buses = [];
$status_count = ["Running" => 0, "Stopped" => 0, "Refueling" => 0];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
        $status = $row['status'];
        $status_count[$status] = isset($status_count[$status]) ? $status_count[$status] + 1 : 1;
    }
} else {
    error_log("No buses found in the database.");
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
            margin: 0;
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
            padding: 10px 20px;
            margin-left: 10px;
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
            overflow: auto;
        }

        canvas {
            max-width: 500px;
            width: 100%;
            max-height: 300px;
            margin: 0 auto;
            display: block;
        }

        #shareModal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        #shareModal button {
            margin-top: 10px;
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
            <div class="stats">
                <div>🟢 Running: <?= $status_count["Running"] ?></div>
                <div>🔴 Stopped: <?= $status_count["Stopped"] ?></div>
                <div>🟡 Refueling: <?= $status_count["Refueling"] ?></div>
            </div>
        </div>

        <div class="card" id="manage">
            <h2>✏ Manage Bus Status & Location</h2>
            <form method="POST" id="locationForm">
                <input type="hidden" name="update_bus" value="1">
                <select name="bus_id" id="bus_id" required>
                    <option value="">--Select Bus--</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?= $bus['id'] ?>"><?= $bus['bus_number'] ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" id="status" required>
                    <option value="Running">Running</option>
                    <option value="Stopped">Stopped</option>
                    <option value="Refueling">Refueling</option>
                </select>
                <input type="text" name="location" id="location" placeholder="Enter location" required>
                <input type="text" name="google_maps_link" id="google_maps_link" placeholder="Google Maps URL (auto-filled)" readonly>
                <input type="hidden" id="latitude" name="latitude">
                <input type="hidden" id="longitude" name="longitude">
                <label><input type="checkbox" name="live_tracking" id="live_tracking"> Enable Live Tracking</label>
                <br>
                <button type="button" class="live-btn" onclick="getLiveLocation()">📍 Get Live Location</button>
                <button type="submit" style="display:none;" id="submitBtn">Update 🚍</button>
                <button type="button" class="live-btn" id="shareLiveLocationBtn" style="display:none;">📤 Share Live Location</button>
            </form>
            <div id="locationStatus" style="margin-top: 10px; display: none;"></div>
            <div id="shareModal">
                <h3>Share Live Location</h3>
                <p>Share this link with users to view live location:</p>
                <input type="text" id="shareLink" readonly style="width: 100%; padding: 10px; margin: 10px 0;">
                <button onclick="copyToClipboard()">Copy Link</button>
                <button onclick="closeModal()">Close</button>
            </div>
        </div>

        <div class="card" id="logs">
            <h2>📅 Status Logs</h2>
            <form method="GET">
                <select name="bus_number">
                    <option value="">All Buses</option>
                    <?php foreach ($buses as $bus): ?>
                        <option value="<?= $bus['bus_number'] ?>" <?= isset($_GET['bus_number']) && $_GET['bus_number'] == $bus['bus_number'] ? 'selected' : '' ?>><?= $bus['bus_number'] ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start_date" value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : '' ?>">
                <input type="date" name="end_date" value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : '' ?>">
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
                            <td><span class="badge <?= htmlspecialchars($log['status']) ?>"><?= htmlspecialchars($log['status']) ?></span></td>
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
                    | <button class="live-btn" onclick="shareLiveLocation(<?= $bus['id'] ?>)">📤 Share Live Location</button>
                </p>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('.navbar a');
            const cards = document.querySelectorAll('.card');

            function showSection(sectionId) {
                cards.forEach(card => {
                    card.classList.remove('active');
                    if (card.id === sectionId) {
                        card.classList.add('active');
                    }
                });
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('data-section') === sectionId) {
                        link.classList.add('active');
                    }
                });
                history.pushState({}, '', '?section=' + sectionId);
            }

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    if (!link.href.includes('logout.php')) {
                        e.preventDefault();
                        const sectionId = link.getAttribute('data-section');
                        showSection(sectionId);
                    }
                });
            });

            const urlParams = new URLSearchParams(window.location.search);
            const initialSection = urlParams.get('section') || 'overview';
            showSection(initialSection);

            const ctx = document.getElementById('statusChart')?.getContext('2d');
            if (ctx) {
                new Chart(ctx, {
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
                        maintainAspectRatio: true,
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

            // Get live location function
            window.getLiveLocation = function() {
                console.log('Attempting to get live location...');
                const locationStatus = document.getElementById('locationStatus');
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const locationInput = document.getElementById('location');
                const googleMapsInput = document.getElementById('google_maps_link');
                const shareLiveLocationBtn = document.getElementById('shareLiveLocationBtn');
                const locationForm = document.getElementById('locationForm');

                console.log('DOM Elements:', {
                    locationStatus, latitudeInput, longitudeInput, locationInput, googleMapsInput, shareLiveLocationBtn, locationForm
                });

                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser. Please use a modern browser like Chrome or Firefox.');
                    console.error('Geolocation not supported');
                    return;
                }

                if (!latitudeInput || !longitudeInput || !locationForm || !googleMapsInput || !locationStatus || !locationInput) {
                    alert('Error: One or more required elements not found in the DOM! Check console.');
                    console.error('Missing elements:', {
                        latitudeInput, longitudeInput, locationForm, googleMapsInput, locationStatus, locationInput
                    });
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const latitude = position.coords.latitude.toFixed(6);
                        const longitude = position.coords.longitude.toFixed(6);
                        const accuracy = position.coords.accuracy.toFixed(0);
                        latitudeInput.value = latitude;
                        longitudeInput.value = longitude;
                        const googleMapsLink = `https://www.google.com/maps?q=${latitude},${longitude}`;
                        googleMapsInput.value = googleMapsLink;
                        locationInput.value = `Retrieved Location (Lat: ${latitude}, Long: ${longitude})`; // Populate location field

                        locationStatus.style.display = 'block';
                        locationStatus.innerHTML = `📍 Current Location Retrieved!<br>Accuracy: ${accuracy} meters<br><a href="${googleMapsLink}" target="_blank">View on Map</a>`;
                        console.log('Location retrieved:', { latitude, longitude, accuracy });

                        shareLiveLocationBtn.style.display = 'inline-block'; // Show share button after getting location
                    },
                    (error) => {
                        let errorMessage = 'Geolocation Error: ';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += 'User denied location access. Please allow it in browser settings.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += 'Location information unavailable.';
                                break;
                            case error.TIMEOUT:
                                errorMessage += 'Request timed out. Try again.';
                                break;
                            default:
                                errorMessage += 'Unknown error occurred.';
                        }
                        alert(errorMessage);
                        console.error('Geolocation Error:', error);
                        locationStatus.style.display = 'block';
                        locationStatus.style.color = 'red';
                        locationStatus.textContent = errorMessage;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            };

            // Share live location function for Map View
            window.shareLiveLocation = function(busId) {
    const formData = new FormData();
    formData.append('bus_id', busId);
    formData.append('start_live_tracking', 1);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Response:', data);
        const shareLink = `/dbmsprj/track_bus.php?bus_id=${busId}`; // Path relative to server root
        document.getElementById('shareLink').value = shareLink;
        document.getElementById('shareModal').style.display = 'block';
    })
    .catch(error => console.error('Error:', error));
};

// Update the Manage Buses share function
document.getElementById('shareLiveLocationBtn').addEventListener('click', function() {
    const busId = document.getElementById('bus_id').value;
    if (!busId) {
        alert('Please select a bus first!');
        return;
    }
    const formData = new FormData();
    formData.append('bus_id', busId);
    formData.append('start_live_tracking', 1);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        console.log('Response:', data);
        const shareLink = `/dbmsprj/track_bus.php?bus_id=${busId}`; // Path relative to server root
        document.getElementById('shareLink').value = shareLink;
        document.getElementById('shareModal').style.display = 'block';
    })
    .catch(error => console.error('Error:', error));
});
            // Share live location function for Manage Buses
            document.getElementById('shareLiveLocationBtn').addEventListener('click', function() {
                const busId = document.getElementById('bus_id').value;
                if (!busId) {
                    alert('Please select a bus first!');
                    return;
                }
                const formData = new FormData();
                formData.append('bus_id', busId);
                formData.append('start_live_tracking', 1);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    console.log('Response:', data);
                    const shareLink = `http://localhost/track_bus.php?bus_id=${busId}`;
                    document.getElementById('shareLink').value = shareLink;
                    document.getElementById('shareModal').style.display = 'block';
                })
                .catch(error => console.error('Error:', error));
            });

            // Modal controls
            window.copyToClipboard = function() {
                const shareLink = document.getElementById('shareLink');
                shareLink.select();
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            };

            window.closeModal = function() {
                document.getElementById('shareModal').style.display = 'none';
            };
        });
    </script>
</body>
</html>