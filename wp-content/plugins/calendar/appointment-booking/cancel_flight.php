<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../db_connect.php');
$conn = getDB();

// === DEBUG SETUP ===
$rawInput = file_get_contents("php://input");
file_put_contents(__DIR__ . "/cancel_debug.log", "📥 Raw Input: " . $rawInput . PHP_EOL, FILE_APPEND);

$input = json_decode($rawInput, true);
file_put_contents(__DIR__ . "/cancel_debug.log", "🧠 Decoded Input: " . json_encode($input) . PHP_EOL, FILE_APPEND);

$flightId = $input['flight_id'] ?? $input['id'] ?? null;
$studentPhone = $input['student_phone'] ?? null;

if (!$flightId || !$studentPhone) {
  file_put_contents(__DIR__ . "/cancel_debug.log", "❌ Missing input: flight_id or student_phone" . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'Missing flight ID or phone']);
  exit;
}

// === FETCH FLIGHT ===
$stmt = $conn->prepare("SELECT * FROM wp_flight_schedule WHERE id = ?");
$stmt->execute([$flightId]);
$flight = $stmt->fetch();

if (!$flight) {
  file_put_contents(__DIR__ . "/cancel_debug.log", "❌ Flight not found for ID: $flightId" . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'Flight not found']);
  exit;
}

$studentId = $flight['student_id'] ?? null;
if (!$studentId) {
  file_put_contents(__DIR__ . "/cancel_debug.log", "❌ No student_id found on flight" . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'No student ID associated with this flight']);
  exit;
}

// === LOOK UP STUDENT BY ID ===
$stmt = $conn->prepare("SELECT * FROM wp_students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if (!$student) {
  file_put_contents(__DIR__ . "/cancel_debug.log", "❌ Student not found for ID: $studentId" . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'Student not found']);
  exit;
}

// === NORMALIZE FUNCTION ===
function normalizePhone($p) {
  return preg_replace('/\D/', '', $p ?? '');
}

$normalizedInput = normalizePhone($studentPhone);
$normalizedStudentPhone = normalizePhone($student['phone'] ?? '');

file_put_contents(__DIR__ . "/cancel_debug.log", "🔎 Normalized: Input = $normalizedInput | Student = $normalizedStudentPhone" . PHP_EOL, FILE_APPEND);

if ($normalizedInput !== $normalizedStudentPhone) {
  file_put_contents(__DIR__ . "/cancel_debug.log", "🚫 Phone mismatch — not authorized" . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'Phone mismatch. You are not authorized to cancel this.']);
  exit;
}

// === LOG TO CANCELED TABLE ===
$stmt = $conn->prepare("INSERT INTO wp_canceled_flights 
  (student_name, student_phone, flight_type, cfi_id, tail_number, airport_code, appointment_date, date_booked)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
  $student['first_name'] . ' ' . $student['last_name'],
  $student['phone'],
  $flight['flight_type'] ?? '',
  $flight['cfi_id'] ?? '',
  $flight['tail_number'] ?? '',
  $flight['airport_code'] ?? '',
  $flight['start_time'] ?? '',
  $flight['created_at'] ?? date('Y-m-d H:i:s')
]);

file_put_contents(__DIR__ . "/cancel_debug.log", "✅ Logged cancellation for student $studentId" . PHP_EOL, FILE_APPEND);

// === DELETE FLIGHT ===
$stmt = $conn->prepare("DELETE FROM wp_flight_schedule WHERE id = ?");
$stmt->execute([$flightId]);

file_put_contents(__DIR__ . "/cancel_debug.log", "🗑️ Deleted flight ID $flightId" . PHP_EOL, FILE_APPEND);

echo json_encode(['success' => true]);
