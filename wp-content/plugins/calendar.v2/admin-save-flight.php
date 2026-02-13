<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/appointment-booking/google_calendar_push.php');

$conn = getDB();
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
  echo json_encode(["success" => false, "message" => "Invalid input."]);
  exit;
}

$required = ['flight_type', 'start', 'end', 'cfi_id', 'location'];
foreach ($required as $field) {
  if (empty($input[$field])) {
    echo json_encode(["success" => false, "message" => "Missing field: $field"]);
    exit;
  }
}

$flight_type_id = $input['flight_type'];
$start = $input['start'];
$end = $input['end'];
$tail = $input['tail_number'] ?? null;
$student_name = $input['student_name'] ?? '';
$student_phone = $input['student_phone'] ?? '';
$cfi_id = $input['cfi_id'];
$home_airport = $input['location'];
$flight_id = $input['flight_id'] ?? null;

// Lookup flight type name from wp_flight_types
$stmt = $conn->prepare("SELECT name FROM wp_flight_types WHERE id = ?");
$stmt->execute([$flight_type_id]);
$flight_type_row = $stmt->fetch(PDO::FETCH_ASSOC);
$flight_type = $flight_type_row['name'] ?? 'Unknown';

$student_id = null;
if (!empty($student_name)) {
  $stmt = $conn->prepare("SELECT student_id FROM wp_students WHERE CONCAT(first_name, ' ', last_name) = ? LIMIT 1");
  $stmt->execute([$student_name]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($result && isset($result['student_id'])) {
    $student_id = $result['student_id'];
  }
}

if ($flight_id) {
  $stmt = $conn->prepare("UPDATE wp_flight_schedule SET flight_type=?, start_time=?, end_time=?, tail_number=?, student_id=?, cfi_id=?, home_airport=? WHERE id=?");
  $stmt->execute([$flight_type, $start, $end, $tail, $student_id, $cfi_id, $home_airport, $flight_id]);
} else {
  $stmt = $conn->prepare("INSERT INTO wp_flight_schedule (flight_type, start_time, end_time, tail_number, student_id, cfi_id, home_airport, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
  $stmt->execute([$flight_type, $start, $end, $tail, $student_id, $cfi_id, $home_airport]);
  $flight_id = $conn->lastInsertId();
}

echo json_encode(["success" => true, "flight_id" => $flight_id]);
?>
