CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sap_id BIGINT NOT NULL,
    password VARCHAR(50) NOT NULL,
    role VARCHAR(20) NOT NULL
);
INSERT INTO users (sap_id, password, role) VALUES
(70572300035, 'Stme!2023', 'admin'),
(70572300032, 'Stme!2023', 'student'),
(70572300010, 'Stme!2023', 'student'),
(70572300042, 'Stme!2023', 'student'),
(70572300036, 'Stme!2023', 'admin');




CREATE TABLE buses_track (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_number INT NOT NULL,
    driver_name VARCHAR(50),
    driver_contact VARCHAR(15),
    departure_time TIME,
    location VARCHAR(100),
    status VARCHAR(20),
    latitude DECIMAL(10, 6),
    longitude DECIMAL(10, 6),
    live_tracking BOOLEAN DEFAULT 0,
    capacity INT
);
INSERT INTO buses_track 
(bus_number, driver_name, driver_contact, departure_time, location, status, latitude, longitude, live_tracking, capacity) 
VALUES
(11, 'Pasha', '9876543210', '07:30:00', 'jadcherla', 'Running', 18.823791, 78.142174, 0, NULL),
(12, 'srinivas', '9876543211', '08:00:00', 'svs', 'Stopped', 18.70128, 78.084402, 0, NULL),
(13, 'venkatesh', '9876543212', '08:15:00', 'kritunga', 'Stopped', 17.422746, 78.446892, 1, NULL),
(14, 'ali', '9876543213', '07:46:00', 'bhootpur flyover', 'Refueling', 17.422746, 78.446692, 0, NULL),
(15, 'kumar', '9876543214', '08:30:00', 'svs', 'Running', 18.813575, 78.145003, 0, NULL);




CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    bus_id INT,
    sent_by INT, -- admin id from users table
    type ENUM('info', 'warning', 'error') DEFAULT 'info',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id),
    FOREIGN KEY (sent_by) REFERENCES users(id)
);
INSERT INTO notifications (message, bus_id, sent_by, type)
VALUES 
('Bus 1 is delayed due to traffic.', 1, 1, 'warning'),
('Bus 2 has started from Mbnr bus stand.', 2, 1, 'info'),
('Bus 3 is currently at Kritunga for refueling.', 3, 5, 'info'),
('Bus 4 is running on time from Cafeteria Stop.', 4, 1, 'info'),
('Bus 5 is stopped temporarily. Further updates soon.', 5, 5, 'warning'),
('Bus 3 will resume at 08:45 AM after refueling.', 3, 1, 'info'),
('Bus 2 is cancelled today due to maintenance.', 2, 5, 'error');



CREATE TABLE bus_status_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    status VARCHAR(50), -- e.g. Running, Refueling
    location VARCHAR(255),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
INSERT INTO bus_status_log (bus_id, status, location)
VALUES 
-- Bhoothpur Bus
(1, 'Refueling', 'SVS'),
(1, 'Running', 'SVS - Highway Exit'),

-- Malabar Bus
(2, 'Stopped', 'Mbnr Bus Stand'),
(2, 'Running', 'Main Bypass'),

-- Jadcherla Bus
(3, 'Refueling', 'Kritunga'),
(3, 'Running', 'National Highway'),

-- Shadnagar Bus
(4, 'Running', 'Cafeteria Stop'),
(4, 'Stopped', 'Tech Block'),

-- Hyderabad Bus
(5, 'Stopped', 'Sports Complex'),
(5, 'Running', 'Gate 1');



CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    contact VARCHAR(15),
    photo_url VARCHAR(255),
    license_number VARCHAR(50),
    assigned_bus_id INT,
    FOREIGN KEY (assigned_bus_id) REFERENCES buses(id)
);
INSERT INTO drivers (name, contact, photo_url, license_number, assigned_bus_id)
VALUES 
-- Bus 1: Bhoothpur
('Pasha', '9876543210', 'uploads/pasha.jpg', 'DL123456789', 1),

-- Bus 2: Malabar
('Srinivas', '9123456789', 'uploads/srinivas.jpg', 'DL234567890', 2),

-- Bus 3: Jadcherla
('Venkatesh', '9988776655', 'uploads/venkatesh.jpg', 'DL345678901', 3),

-- Bus 4: Shadnagar
('Ali', '9876512345', 'uploads/ali.jpg', 'DL456789012', 4),

-- Bus 5: Hyderabad
('Kumar', '9012345678', 'uploads/kumar.jpg', 'DL567890123', 5);




CREATE TABLE schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    departure_time TIME,
    arrival_time TIME,
    source VARCHAR(100),
    destination VARCHAR(100),
    active ENUM('yes', 'no') DEFAULT 'yes',
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
INSERT INTO schedules (bus_id, departure_time, arrival_time, source, destination, active)
VALUES 
-- Bus 1: Bhoothpur
(1, '07:15:00', '08:30:00', 'Bhoothpur', 'NMIMS Campus', 'yes'),

-- Bus 2: Malabar
(2, '07:00:00', '08:20:00', 'Malabar Bus Stand', 'NMIMS  Campus', 'yes'),

-- Bus 3: Jadcherla
(3, '06:45:00', '08:10:00', 'Jadcherla', 'NMIMS  Campus', 'yes'),

-- Bus 4: Shadnagar
(4, '07:30:00', '08:40:00', 'Shadnagar', 'NMIMS Campus', 'yes'),

-- Bus 5: Hyderabad
(5, '06:30:00', '08:45:00', 'Hyderabad (LB Nagar)', 'NMIMS  Campus', 'yes');





CREATE TABLE admin_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id)
);
INSERT INTO admin_messages (admin_id, title, content)
VALUES
(1, 'Bus Delay Notice', 'Bus to MBNR Bus Stand will be delayed by 15 minutes due to traffic. Please plan accordingly.'),
(5, 'Fuel Refill Update', 'Bus from SVS has completed refueling and is ready to resume its route.'),
(1, 'Maintenance Alert', 'Bus from Kritunga will undergo service tomorrow. Please use alternative transport.'),
(5, 'Holiday Notification', 'No buses will run on Sunday due to campus maintenance.'),
(1, 'Route Change', 'The route from Sports Complex will be diverted via Gate B due to roadwork.');





CREATE TABLE gps_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    latitude DECIMAL(10, 6),
    longitude DECIMAL(10, 6),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
INSERT INTO gps_locations (bus_id, latitude, longitude)
VALUES 
-- Bus 1: Bhoothpur
(1, 16.738220, 78.004761),  -- Near SVS Campus
(1, 16.734900, 78.005300),  -- SVS Parking

-- Bus 2: Malabar
(2, 16.754320, 78.013540),  -- Mbnr Bus Stand
(2, 16.748900, 78.017600),  -- Main Road Junction

-- Bus 3: Jadcherla
(3, 16.768901, 78.098761),  -- Kritunga Area
(3, 16.770000, 78.095000),  -- Highway Turn

-- Bus 4: Shadnagar
(4, 17.068900, 78.350000),  -- Cafeteria Junction
(4, 17.070000, 78.355000),  -- Tech Block

-- Bus 5: Hyderabad
(5, 17.385044, 78.486671),  -- LB Nagar
(5, 17.396000, 78.478000);  -- Exit Highway





CREATE TABLE maintenance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    type ENUM('refueling', 'service') NOT NULL,
    details TEXT,
    cost DECIMAL(10, 2),
    date DATE,
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);

INSERT INTO maintenance_logs (bus_id, type, details, cost, date)
VALUES 
-- Bus 1: Bhoothpur
(1, 'refueling', 'Diesel top-up at SVS fuel station', 3200.00, '2025-04-06'),
(1, 'service', 'Routine engine check-up and oil change', 1500.00, '2025-03-30'),

-- Bus 2: Malabar
(2, 'service', 'Brake pads replaced and AC service', 4200.00, '2025-04-05'),

-- Bus 3: Jadcherla
(3, 'refueling', 'Diesel filled at Kritunga station', 2950.50, '2025-04-07'),

-- Bus 4: Shadnagar
(4, 'service', 'Full maintenance including suspension and tire rotation', 5800.00, '2025-03-29'),

-- Bus 5: Hyderabad
(5, 'refueling', 'Tanked up at Fuel Station A near Sports Complex', 3100.00, '2025-04-06'),
(5, 'service', 'Battery check and coolant refill', 1800.00, '2025-04-01');




CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bus_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    date DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (bus_id) REFERENCES buses(id)
);
INSERT INTO feedback (user_id, bus_id, rating, comments)
VALUES
(2, 1, 4, 'Smooth ride, but could be more punctual.'),
(3, 2, 5, 'Very comfortable and on time.'),
(4, 3, 3, 'Bus stopped midway due to issues.'),
(2, 4, 5, 'Excellent service by the driver.'),
(3, 5, 2, 'Bus was late and no notification was sent.');

CREATE TABLE bus_timings (
    timing_id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    time TIME NOT NULL,
    status VARCHAR(50),
    FOREIGN KEY (bus_id) REFERENCES buses_track(bus_id)
);INSERT INTO bus_timings (bus_id, time, status) VALUES
(1, '08:00:00', 'Running'),
(2, '08:15:00', 'Delayed'),
(3, '08:30:00', 'Refueling'),
(1, '10:00:00', 'Running'),
(2, '10:15:00', 'Stopped')





-- 1. View All Students
SELECT * FROM Students;

-- 2. Buses with Capacity Greater Than 50
SELECT * FROM Buses_track WHERE capacity > 50;

-- 3. Students in 3rd Year
SELECT * FROM Students WHERE year = 3;

-- 4. Drivers with More Than 5 Years of Experience
ALTER TABLE drivers ADD COLUMN experience_years INT;
UPDATE drivers SET experience_years = 6 WHERE name = 'Pasha';
UPDATE drivers SET experience_years = 3 WHERE name = 'Srinivas';
UPDATE drivers SET experience_years = 8 WHERE name = 'Venkatesh';
UPDATE drivers SET experience_years = 5 WHERE name = 'Ali';
UPDATE drivers SET experience_years = 7 WHERE name = 'Kumar';
SELECT * FROM Drivers WHERE experience_years > 5;

-- 5. Students Living in Hostel
SELECT * FROM Students WHERE address LIKE '%Hostel%';

-- 6. Bus and Driver Details
SELECT 
  Buses_track.bus_id, 
  Buses_track.route, 
  Drivers.name 
FROM 
  Buses_track
JOIN 
  Drivers 
ON 
  Buses_track.driver_id = Drivers.id
LIMIT 0, 25;


-- 7. Student Names and Their Bus Routes
SELECT Students.name, Buses_track.route 
FROM Students 
JOIN Buses_track ON Students.bus_id = Buses_track.bus_id;

-- 8. Bus Timing with Route and Driver Info

SELECT 
    bus_timings.time, 
    bus_timings.status, 
    buses_track.route, 
    drivers.name 
FROM 
    bus_timings
JOIN 
    buses_track ON bus_timings.bus_id = buses_track.bus_id
JOIN 
    drivers ON buses_track.driver_id = drivers.assigned_bus_id
LIMIT 0, 25;



-- 9. Duplicate - Bus Timing with Route and Driver Info
SELECT Bus_Timings.time, Bus_Timings.status, Buses_track.route, Drivers.name 
FROM Bus_Timings 
JOIN Buses_track ON Bus_Timings.bus_id = Buses_track.bus_id 
JOIN Drivers ON Buses_track.driver_id = Drivers.id;



-- 10. Student Names with Bus Status
SELECT Students.name, Bus_Timings.status 
FROM Students 
JOIN Bus_Timings ON Students.bus_id = Bus_Timings.bus_id;

-- 11. Drivers Operating Running Buses
SELECT DISTINCT Drivers.name 
FROM Drivers 
JOIN Buses_track ON Drivers.id = Buses_track.driver_id 
JOIN Bus_Timings ON Buses_track.bus_id = Bus_Timings.bus_id 
WHERE Bus_Timings.status = 'Running';



-- 12. Count of Students Per Bus
SELECT bus_id, COUNT(*) AS student_count 
FROM Students 
GROUP BY bus_id;

-- 13. Average Experience of Drivers
SELECT AVG(experience_years) AS avg_experience FROM Drivers;

-- 14. Bus with Highest Number of Students
SELECT bus_id, COUNT(*) AS total 
FROM Students 
GROUP BY bus_id 
ORDER BY total DESC 
LIMIT 1;

-- 15. Total Number of Students
SELECT COUNT(*) FROM Students;

-- 16. Bus Count Per Route
SELECT route, COUNT(*) AS total_buses 
FROM Buses_track 
GROUP BY route;

-- 17. Students Sharing Same Bus as 'mounika'
SELECT * 
FROM Students 
WHERE bus_id = (SELECT bus_id FROM Students WHERE name = 'mounika');

-- 18. Drivers Driving Overloaded Buses (more than 40 students)
SELECT DISTINCT Drivers.name 
FROM Drivers 
JOIN Buses_track ON Drivers.id = Buses_track.driver_id 
WHERE Buses_track.bus_id IN (
  SELECT bus_id 
  FROM Students 
  GROUP BY bus_id 
  HAVING COUNT(*) > 40
);

-- 19. Buses Currently Delayed
SELECT * 
FROM Buses_track 
WHERE bus_id IN (
  SELECT bus_id FROM Bus_Timings WHERE status = 'Delayed'
);

-- 20. Students Without Assigned Bus
SELECT * FROM Students WHERE bus_id IS NULL;

-- 21. Drivers Not Assigned to Any Bus
SELECT * 
FROM Drivers 
WHERE id NOT IN (SELECT id FROM Buses);

-- 22. Update Status to Running for Bus 102
UPDATE Bus_Timings SET status = 'Running' WHERE bus_id = 102;

-- 23. Update Student 1005's Bus to 103
UPDATE Students SET bus_id = 103 WHERE student_id = 1005;

-- 24. Increment Experience for All Drivers by 1
UPDATE Drivers SET experience_years = experience_years + 1;

-- 25. Update Route 'North' to 'North-East'
UPDATE Buses_track SET route = 'North-East' WHERE route = 'HYderabad';

-- 26. Mark Buses as Delayed if Time > 08:30 AM
UPDATE Bus_Timings 
SET status = 'Delayed' 
WHERE time > '08:30:00';

-- 27. Delete All Final Year Students (Year = 4)
DELETE FROM Students WHERE year = 4;

-- 28. Delete Bus Timings with Status 'Stopped'
DELETE FROM Bus_Timings WHERE status = 'Stopped';

-- 29. Delete Drivers with 0 Years of Experience
DELETE FROM Drivers WHERE experience_years = 0;

-- 30. (Duplicate) Delete Drivers with 0 Years of Experience
DELETE FROM Drivers WHERE experience_years = 0;

-- 31. Delete Buses Without Drivers
DELETE FROM Buses_track WHERE driver_id IS NULL;

-- 32. Delete Students Without Bus Assigned
DELETE FROM Students WHERE bus_id IS NULL;

-- 33. Earliest Bus Timing Entry
SELECT * 
FROM Bus_Timings 
ORDER BY time ASC 
LIMIT 1;

-- 34. Total Students Per Driver and Bus
SELECT Drivers.name, Buses_track.bus_id, COUNT(Students.student_id) AS total_students 
FROM Drivers 
JOIN Buses_track ON Drivers.id = Buses_track.driver_id 
LEFT JOIN Students ON Buses_track.bus_id = Students.bus_id 
GROUP BY Drivers.name, Buses_track.bus_id;

-- 35. Route with Maximum Number of Students
SELECT Buses_track.route, COUNT(Students.student_id) AS total 
FROM Students 
JOIN Buses_track ON Students.bus_id = Buses_track.bus_id 
GROUP BY Buses_track.route 
ORDER BY total DESC 
LIMIT 1;

-- 36. Students on Delayed Buses
SELECT Students.name 
FROM Students 
JOIN Bus_Timings ON Students.bus_id = Bus_Timings.bus_id 
WHERE Bus_Timings.status = 'Delayed';

-- 37. Bus Count Per Driver
SELECT driver_id, COUNT(bus_id) AS bus_count 
FROM Buses_track 
GROUP BY driver_id;