<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);

  $student_id = intval($input['student_id'] ?? 0);
  $phone = trim($input['phone'] ?? '');
  $appointment_type = trim($input['appointment_type'] ?? '');
  $tail_number = trim($input['tail_number'] ?? '');
  $start_hobbs = floatval($input['start_hobbs'] ?? 0);
  $start_tach = floatval($input['start_tach'] ?? 0);
  $description_input = trim($input['description'] ?? '');
  $cfi_id = intval($input['cfi_id'] ?? 0);

  if (empty($appointment_type) || empty($tail_number)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing appointment type or tail number.']);
    exit;
  }

  $db = getDB();

  if ($student_id > 0) {
    $stmt = $db->prepare("SELECT * FROM wp_students WHERE student_id = ?");
    $stmt->execute([$student_id]);
  } else if (!empty($phone)) {
    $stmt = $db->prepare("SELECT * FROM wp_students WHERE phone = ?");
    $stmt->execute([$phone]);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'No student ID or phone provided.']);
    exit;
  }

  $student = $stmt->fetch();

  if (!$student) {
    echo json_encode(['status' => 'error', 'message' => 'Student not found.']);
    exit;
  }

  $student_id = $student['student_id'];

  $full_description = $appointment_type;
  if (!empty($description_input)) {
    $full_description .= ' - ' . $description_input;
  }

  // Fetch aircraft_factor from wp_aircraft
$stmt = $db->prepare("SELECT aircraft_factor FROM wp_aircraft WHERE tail_number = ?");
$stmt->execute([$tail_number]);
$aircraft = $stmt->fetch();

$plane_factor = $aircraft ? floatval($aircraft['aircraft_factor']) : 1.00;

$stmt = $db->prepare("INSERT INTO wp_flight_logs (
  student_id, cfi_id, tail_number, start_hobbs, start_tach,
  appointment_type, plane_factor, description, flight_category, status, checkout_time
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

$stmt->execute([
  $student_id,
  $cfi_id,
  $tail_number,
  $start_hobbs,
  $start_tach,
  $appointment_type,
  $plane_factor,
  $full_description,
  'Student Flight',
  'Flying'
]);


  echo json_encode(['status' => 'success', 'message' => 'Checkout recorded successfully.']);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}

