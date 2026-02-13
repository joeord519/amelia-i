<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
session_start();

$conn = getDB();

// Get form values
$flightCategory = $_POST['flightType'] ?? '';
$studentId = $_POST['studentId'] ?? null;
$cfiId = $_POST['cfiId'] ?? null;
$tailNumber = $_POST['tail_number'] ?? '';
$flightDate = $_POST['date'] ?? '';
$flightDuration = floatval($_POST['flightHours'] ?? 0);
$groundTime = floatval($_POST['groundHours'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
if (!$flightCategory || (!$studentId && !$cfiId) || !$flightDate) {
    http_response_code(400);
    echo "Missing required fields.";
    exit;
}

// === SOF TIME ===
if ($flightCategory === 'SOF Time') {
    try {
        $stmt = $conn->prepare("INSERT INTO wp_flight_logs (flight_category, cfi_id, flight_date, ground_time, student_notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$flightCategory, $cfiId, $flightDate, $groundTime, $notes]);
        header("Location: admin-panel.php?success=1");
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Failed to insert SOF Time: " . $e->getMessage();
        exit;
    }
}

// ✅ Lookup flight_time_factor using tail number
$flight_time_factor = 1.0;
if ($tailNumber) {
    $stmt = $conn->prepare("SELECT flight_time_factor FROM wp_aircraft WHERE tail_number = ?");
    $stmt->execute([$tailNumber]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $flight_time_factor = floatval($row['flight_time_factor']);
    }
}

// ✅ Insert into wp_flight_logs
try {
    $stmt = $conn->prepare("INSERT INTO wp_flight_logs (flight_category, student_id, cfi_id, tail_number, total_flight_time, ground_time, flight_date, student_notes, plane_factor)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $flightCategory,
        $studentId,
        $cfiId,
        $tailNumber,
        $flightDuration,
        $groundTime,
        $flightDate,
        $notes,
        $flight_time_factor
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "Insert error: " . $e->getMessage();
    exit;
}

// ✅ Deduct hours from wp_students
if ($studentId) {
    if ($flightDuration > 0 && $tailNumber) {
        $deductAircraft = $flightDuration * $flight_time_factor;
        $stmt = $conn->prepare("UPDATE wp_students SET aircraft_hours_remaining = aircraft_hours_remaining - ? WHERE student_id = ?");
        $stmt->execute([$deductAircraft, $studentId]);
    }

    if ($cfiId && ($flightDuration > 0 || $groundTime > 0)) {
        $deductInstructor = $flightDuration + $groundTime;
        $stmt = $conn->prepare("UPDATE wp_students SET instructor_hours_remaining = instructor_hours_remaining - ? WHERE student_id = ?");
        $stmt->execute([$deductInstructor, $studentId]);
    }
}

$cache_buster = time();
header("Location: admin-panel.php?success=1&cb=$cache_buster");
exit;
