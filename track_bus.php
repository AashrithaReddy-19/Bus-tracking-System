<?php
session_start();
include "config.php";
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch all buses or a specific bus based on a parameter (e.g., bus_id or bus_number)
$bus_id = isset($_GET['bus_id']) ? intval($_GET['bus_id']) : null;
$where = $bus_id ? "WHERE id = $bus_id" : "";
$sql = "SELECT id, bus_number, status, location, latitude, longitude, live_tracking FROM buses $where";
$result = $conn->query($sql);
$buses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
    }
} else {
    $buses = []; // No buses found
}

// Fetch schedules with JOIN to get bus number
$schedule_result = $conn->query("
    SELECT schedules.departure_time, schedules.arrival_time, buses.bus_number
    FROM schedules
    JOIN buses ON schedules.bus_id = buses.id
    ORDER BY schedules.departure_time
    LIMIT 5
");
if (!$schedule_result) {
    die("Error fetching schedules: " . $conn->error);
}

// Fetch driver profiles
$drivers_result = $conn->query("SELECT * FROM drivers LIMIT 5");
if (!$drivers_result) {
    die("Error fetching drivers: " . $conn->error);
}

// Fetch recent notifications
$notifications_result = $conn->query("SELECT message, type, created_at FROM notifications ORDER BY created_at DESC LIMIT 10");
if (!$notifications_result) {
    die("Error fetching notifications: " . $conn->error);
}

// Fetch recent status updates
$status_updates = $conn->query("SELECT * FROM bus_status_log ORDER BY timestamp DESC LIMIT 5");
if (!$status_updates) {
    die("Error fetching status updates: " . $conn->error);
}

// Fetch admin notices
$admin_notices = $conn->query("SELECT * FROM admin_messages ORDER BY created_at DESC LIMIT 5");
if (!$admin_notices) {
    die("Error fetching admin messages: " . $conn->error);
}

// Check if a bus ID is selected for detailed view
$bus = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $bus_id = intval($_GET['id']);
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
    <title>Track Bus - Smart Bus Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary: #007bff;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .navbar {
            background: linear-gradient(45deg, var(--primary), #0056b3);
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

        h1, h2, h3 {
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 600;
        }

        h2::after, h3::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50%;
            height: 3px;
            background: linear-gradient(to right, var(--primary), transparent);
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            padding: 20px;
            transition: transform 0.3s ease;
            display: none;
        }

        .card.active {
            display: block;
            opacity: 1;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: linear-gradient(45deg, var(--primary), #0056b3);
            color: white;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        tr {
            transition: background 0.3s ease;
        }

        tr:hover {
            background: rgba(0, 123, 255, 0.05);
        }

        .bus-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .bus-card:hover {
            transform: translateY(-5px);
        }

        .bus-card h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .bus-card p {
            margin: 5px 0;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            display: inline-block;
            transition: transform 0.2s ease;
            color: white;
        }

        .badge:hover {
            transform: scale(1.1);
        }

        .Running, .success { background: var(--success); }
        .Stopped, .danger { background: var(--danger); }
        .Refueling, .warning { background: var(--warning); color: var(--dark); }
        .info { background: var(--info); }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-running { background: var(--success); } /* Updated class name */
        .status-stopped { background: var(--danger); } /* Updated class name */
        .status-refueling { background: var(--warning); } /* Updated class name */

        a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            color: var(--secondary);
            font-size: 18px;
        }

        #live-map {
            height: 300px;
            width: 100%;
            margin-top: 20px;
            border-radius: 10px;
        }

        .chart-container {
            margin: 20px 0;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .chart-bar {
            height: 20px;
            margin: 10px 0;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
            background: #e9ecef;
        }

        .chart-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), #0056b3);
            transition: width 1s ease-in-out;
        }

        .chart-label {
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        @media (max-width: 768px) {
            .navbar .nav-container {
                flex-direction: column;
                gap: 10px;
            }

            .navbar .nav-links {
                flex-direction: column;
                text-align: center;
            }

            .container {
                margin-top: 120px;
                padding: 0 10px;
            }

            th, td {
                padding: 10px;
                font-size: 14px;
            }

            .card, .bus-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar">
        <div class="nav-container">
            <div class="logo">BusTrack</div>
            <div class="nav-links">
                <a data-section="buses">Buses</a>
                <a data-section="drivers">Drivers</a>
                <a data-section="schedules">Schedules</a>
                <a data-section="notifications">Notifications</a>
                <a data-section="notices">Notices</a>
            </div>
        </div>
    </div>

    <div class="container">
        <h1>🚌 Track Bus Location</h1>

        <!-- Bus List Section -->
        <div class="card" id="buses">
            <h2>Select a Bus to Track</h2>
            <table>
                <thead>
                    <tr>
                        <th>Bus Number</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Track</th>
                        <th>Share Live</th> <!-- Added column -->
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buses as $bus_item): ?>
                        <tr>
                            <td><?= htmlspecialchars($bus_item['bus_number']); ?></td>
                            <td><?= htmlspecialchars($bus_item['location']); ?></td>
                            <td>
                                <span class="status-indicator status-<?= strtolower($bus_item['status']); ?>"></span>
                                <?= htmlspecialchars($bus_item['status']); ?>
                            </td>
                            <td><a href="?id=<?= $bus_item['id']; ?>">View Details</a></td>
                            <td>
                                <?php if ($bus_item['live_tracking']): ?>
                                    <a href="track_bus.php?bus_id=<?= $bus_item['id']; ?>" target="_blank">Share Link</a>
                                <?php else: ?>
                                    <span>Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($buses)): ?>
                <p class="no-data">No buses available to track.</p>
            <?php endif; ?>
        </div>

        <!-- Bus Details Section -->
        <?php if ($bus): ?>
            <div class="card active" id="bus-details">
                <h2>Bus Details</h2>
                <div class="bus-card">
                    <h3>Bus: <?= htmlspecialchars($bus['bus_number']) ?></h3>
                    <p>Status: <span class="badge <?= htmlspecialchars($bus['status']) ?>"><?= htmlspecialchars($bus['status']) ?></span></p>
                    <p>Location: <?= htmlspecialchars($bus['location']) ?></p>
                    <?php if ($bus['latitude'] && $bus['longitude']): ?>
                        <p>Coordinates: <span id="coords"><?= $bus['latitude'] ?>, <?= $bus['longitude'] ?></span></p>
                        <p><a href="https://www.google.com/maps?q=<?= $bus['latitude'] ?>,<?= $bus['longitude'] ?>" target="_blank">View on Google Maps</a></p>
                        <?php if ($bus['live_tracking']): ?>
                            <p>Live Tracking: Enabled</p>
                            <div id="live-map"></div> <!-- Live map container -->
                        <?php else: ?>
                            <p>Live Tracking: Disabled</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No coordinates available.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Drivers Section -->
        <div class="card" id="drivers">
            <h2>Driver Profiles</h2>
            <table>
                <thead><tr><th>Name</th><th>Contact</th><th>License</th></tr></thead>
                <tbody>
                    <?php while ($row = $drivers_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['name']); ?></td>
                            <td><?= htmlspecialchars($row['contact']); ?></td>
                            <td><?= htmlspecialchars($row['license_number']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Schedules Section -->
        <div class="card" id="schedules">
            <h2>Bus Schedule</h2>
            <table>
                <thead><tr><th>Bus Number</th><th>Departure</th><th>Arrival</th></tr></thead>
                <tbody>
                    <?php while ($row = $schedule_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['bus_number']); ?></td>
                            <td><?= htmlspecialchars($row['departure_time']); ?></td>
                            <td><?= htmlspecialchars($row['arrival_time']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="chart-container">
                <h3>Schedule Overview</h3>
                <?php 
                $schedule_result->data_seek(0);
                while ($row = $schedule_result->fetch_assoc()): ?>
                    <div class="chart-label">
                        <span><?= htmlspecialchars($row['bus_number']); ?></span>
                        <span><?= htmlspecialchars($row['departure_time']); ?></span>
                    </div>
                    <div class="chart-bar">
                        <div class="chart-fill" style="width: <?php echo rand(20, 80); ?>%;"></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="card" id="notifications">
            <h2>Recent Notifications</h2>
            <?php if ($notifications_result && $notifications_result->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Message</th><th>Type</th><th>Time</th></tr></thead>
                    <tbody>
                        <?php while ($row = $notifications_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['message']); ?></td>
                                <td><span class="badge <?= strtolower($row['type']); ?>"><?= ucfirst($row['type']); ?></span></td>
                                <td><?= htmlspecialchars($row['created_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data">No notifications available</p>
            <?php endif; ?>
        </div>

        <!-- Notices Section -->
        <div class="card" id="notices">
            <h2>Admin Notices</h2>
            <?php if ($admin_notices && $admin_notices->num_rows > 0): ?>
                <table>
                    <thead><tr><th>Title</th><th>Content</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php while ($row = $admin_notices->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['title'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['content'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($row['created_at'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data">No admin notices available</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const navLinks = document.querySelectorAll('.navbar a');
            const cards = document.querySelectorAll('.card');

            // Function to show a specific section
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
            }

            // Add click event listeners to nav links
            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const sectionId = link.getAttribute('data-section');
                    showSection(sectionId);
                });
            });

            // Show "buses" section by default, or "bus-details" if a bus is selected
            <?php if ($bus): ?>
                showSection('bus-details');
            <?php else: ?>
                showSection('buses');
            <?php endif; ?>

            // Live Tracking Logic
            <?php if ($bus && $bus['live_tracking'] && $bus['latitude'] && $bus['longitude']): ?>
                const busId = <?= $bus['id']; ?>;
                const initialLat = <?= $bus['latitude']; ?>;
                const initialLng = <?= $bus['longitude']; ?>;

                // Initialize the map
                const map = L.map('live-map').setView([initialLat, initialLng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                let marker = L.marker([initialLat, initialLng]).addTo(map)
                    .bindPopup('Bus: <?= htmlspecialchars($bus['bus_number']) ?>').openPopup();

                // Function to update bus location
                function updateBusLocation() {
                    fetch(`track_bus.php?bus_id=${busId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const lat = data.latitude;
                                const lng = data.longitude;
                                marker.setLatLng([lat, lng]);
                                map.setView([lat, lng]);
                                document.getElementById('coords').textContent = `${lat}, ${lng}`;
                                console.log(`Bus ${busId} updated to (${lat}, ${lng})`);
                            } else {
                                console.error('Error from track_bus.php:', data.error);
                            }
                        })
                        .catch(error => console.error('Fetch error:', error));
                }

                // Update every 5 seconds
                setInterval(updateBusLocation, 5000);
                updateBusLocation(); // Initial update
            <?php endif; ?>
        });
    </script>
</body>
</html>