<?php
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$student_id   = $input['student_id'] ?? null;
$tail_number  = $input['tail_number'] ?? null;
$flight_type  = $input['flight_type'] ?? 'Dual';
$hobbs_start  = $input['hobbs_start'] ?? null;
$tach_start   = $input['tach_start'] ?? null;

if (!$student_id || !$tail_number || !$hobbs_start || !$tach_start) {
  echo json_encode(['status' => 'error', 'error' => 'Missing required fields.']);
  exit;
}

// Get student balances
$stmt = $pdo->prepare("SELECT aircraft_hours_remaining, instructor_hours_remaining FROM wp_students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
  echo json_encode(['status' => 'error', 'error' => 'Student not found.']);
  exit;
}

$aircraft_hours = $student['aircraft_hours_remaining'];
$instructor_hours = $student['instructor_hours_remaining'];

// Required minimums
$requires_cfi = in_array($flight_type, ['Dual']);
$required_aircraft = 1.5; // You can update this dynamically
$required_instructor = $requires_cfi ? 1.5 : 0;

if ($aircraft_hours < $required_aircraft) {
  echo json_encode(['status' => 'error', 'error' => 'Not enough aircraft hours remaining.']);
  exit;
}

if ($requires_cfi && $instructor_hours < $required_instructor) {
  echo json_encode(['status' => 'error', 'error' => 'Not enough instructor hours remaining.']);
  exit;
}

// ✅ Step 1: Get the PIN from Igloo
$pin_response = json_decode(file_get_contents('generate_pin.php?tail=' . urlencode($tail_number)), true);
if (!isset($pin_response['pin_code'])) {
  echo json_encode(['status' => 'error', 'error' => 'PIN generation failed.']);
  exit;
}

$pin_code = $pin_response['pin_code'];
$pin_exp = $pin_response['expires_at'] ?? null;

// ✅ Step 2: Insert into wp_flight_logs
$stmt = $pdo->prepare("INSERT INTO wp_flight_logs (
  student_id, tail_number, flight_type, start_hobbs, start_tach, checkout_time, pin_code, pin_expires, status
) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'Checked Out')");

$stmt->execute([
  $student_id,
  $tail_number,
  $flight_type,
  $hobbs_start,
  $tach_start,
  $pin_code,
  $pin_exp
]);

echo json_encode([
  'status' => 'success',
  'pin_code' => $pin_code
]);
