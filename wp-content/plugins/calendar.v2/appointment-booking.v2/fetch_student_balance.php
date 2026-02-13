<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../db_connect.php');
$db = getDB();

header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
$phone = preg_replace('/\D/', '', $phone); // digits only

if (strlen($phone) !== 10) {
  echo json_encode(['error' => 'Invalid phone number']);
  exit;
}

$formattedPhone = sprintf("(%s) %s-%s",
  substr($phone, 0, 3),
  substr($phone, 3, 3),
  substr($phone, 6)
);

try {
  $stmt = $db->prepare("
    SELECT first_name, last_name, email, aircraft_hours_remaining, instructor_hours_remaining, installment_price,
           phase_pay, installments_remaining, installment_flight_hours, installment_instructor_hours
    FROM wp_students 
    WHERE phone = ?
  ");
  $stmt->execute([$formattedPhone]);
  $student = $stmt->fetch();

  if (!$student) {
    echo json_encode(['error' => 'Student not found']);
    exit;
  }

  echo json_encode([
    'name' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
    'email' => $student['email'],
    'phone' => $formattedPhone,
    'phase' => $student['phase_pay'] ?? 'No',
    'ileft' => intval($student['installments_remaining']),
    'cab' => floatval($student['aircraft_hours_remaining']),
    'cib' => floatval($student['instructor_hours_remaining']),
    'iah' => floatval($student['installment_flight_hours']),
    'iih' => floatval($student['installment_instructor_hours']),
    'installment_price' => floatval($student['installment_price'])
  ]);

} catch (Exception $e) {
  echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
  exit;
}
?>
