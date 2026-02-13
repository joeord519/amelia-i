<?php
require_once __DIR__ . '/db_connect.php'; // ✅ Ensure database connection

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// ✅ Collect form data
$aircraft = $_POST['aircraft'] ?? '';
$cfi = $_POST['cfi'] ?? '';
$flight_type = $_POST['flight_type'] ?? '';
$student_id = $_POST['student_id'] ?? '';
$date = $_POST['date'] ?? '';
$start_time = $_POST['start_time'] ?? '';

if (empty($aircraft) || empty($cfi) || empty($flight_type) || empty($student_id) || empty($date) || empty($start_time)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    error_log("❌ Missing fields: aircraft=$aircraft, cfi=$cfi, flight_type=$flight_type, student_id=$student_id, date=$date, start_time=$start_time");
    exit;
}

// ✅ Convert date format if needed
$date_obj = DateTime::createFromFormat('m/d/Y', $date); // MM/DD/YYYY from JavaScript
if (!$date_obj) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format']);
    error_log("❌ Invalid date format received: $date");
    exit;
}
$date_sql = $date_obj->format('Y-m-d'); // Convert to YYYY-MM-DD for MySQL

// ✅ Convert time format
$start_datetime = $date_sql . ' ' . $start_time;
$start_datetime_obj = DateTime::createFromFormat('Y-m-d H:i', $start_datetime);
if (!$start_datetime_obj) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date/time format']);
    error_log("❌ Invalid datetime format: $start_datetime");
    exit;
}

$start_datetime_sql = $start_datetime_obj->format('Y-m-d H:i:s'); // ✅ Format for MySQL

// ✅ Define flight duration based on flight type
$flight_durations = [
    "dual_training" => 2,
    "dual_cross_country" => 4,
    "solo_training" => 2,
    "solo_cross_country" => 4,
    "rental_solo" => 2,
    "rental_cross_country" => 4,
    "checkride" => 8
];

$duration_hours = $flight_durations[$flight_type] ?? 2; // Default to 2 hours if flight type not found
$end_datetime_obj = clone $start_datetime_obj;
$end_datetime_obj->modify("+{$duration_hours} hours");
$end_datetime_sql = $end_datetime_obj->format('Y-m-d H:i:s');

// ✅ Debug log before inserting
error_log("🟢 Ready to insert: aircraft=$aircraft, cfi=$cfi, student_id=$student_id, start=$start_datetime_sql, end=$end_datetime_sql, flight_type=$flight_type");

// ✅ Insert the new booking into wp_flight_schedule
$query = "INSERT INTO wp_flight_schedule (aircraft_id, cfi_id, student_id, start_time, end_time, flight_type, status)
          VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
    error_log("❌ SQL Prepare Error: " . $conn->error);
    exit;
}
$stmt->bind_param("ssssss", $aircraft, $cfi, $student_id, $start_datetime_sql, $end_datetime_sql, $flight_type);
$success = $stmt->execute();

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to insert booking: ' . $stmt->error]);
    error_log("❌ SQL Execution Error: " . $stmt->error);
    $stmt->close();
    exit;
}

$stmt->close();
echo json_encode(['status' => 'success', 'message' => 'Booking successfully added!']);
error_log("✅ Booking successfully added to database!");
?>
