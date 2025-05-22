<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

$host = "localhost";
$user = "root";
$password = "";
$dbname = "bus_tracking";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        error_log("⚠️ Database Connection Failed: " . $conn->connect_error . " - " . date('Y-m-d H:i:s'));
        die("⚠️ Unable to connect to the database. Please try again later.");
    }
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error setting charset: " . $conn->error . " - " . date('Y-m-d H:i:s'));
        die("⚠️ Database configuration error occurred.");
    }
    $conn->query("SELECT 1");
    if ($conn->error) {
        error_log("Database verification failed: " . $conn->error . " - " . date('Y-m-d H:i:s'));
        die("⚠️ Database verification failed.");
    }
    error_log("Database connection successful - " . date('Y-m-d H:i:s'));
} catch (Exception $e) {
    error_log("Critical database error: " . $e->getMessage() . " - " . $e->getTraceAsString() . " - " . date('Y-m-d H:i:s'));
    die("⚠️ An unexpected error occurred. Please contact the administrator.");
}

function verifyBusesTable($conn) {
    try {
        $result = $conn->query("SHOW TABLES LIKE 'buses'");
        if ($result->num_rows == 0) {
            error_log("Buses table not found - " . date('Y-m-d H:i:s'));
            return false;
        }
        return true;
    } catch (Exception $e) {
        error_log("Table verification error: " . $e->getMessage() . " - " . date('Y-m-d H:i:s'));
        return false;
    }
}

if (!verifyBusesTable($conn)) {
    die("⚠️ Required database table 'buses' not found.");
}
?>