<?php
// /wp-content/plugins/checkout.basic/verify_phone.php

require_once(__DIR__ . '/db_connect.php');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
file_put_contents(__DIR__ . '/debug_post.txt', print_r($_POST, true));

header('Content-Type: application/json');

file_put_contents(__DIR__ . '/debug_post.txt', print_r($_POST, true));

try {
  $db = getDB();

  // Sanitize phone input
  $phoneRaw = $_POST['phone'] ?? '';
  $clean = preg_replace('/\D/', '', $phoneRaw);
  if (strlen($clean) !== 10) throw new Exception("Invalid phone format.");
  $formattedPhone = '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6);

  // Look up student
  $stmt = $db->prepare("SELECT * FROM wp_students WHERE phone = :p LIMIT 1");
  $stmt->execute([':p' => $formattedPhone]);
  $student = $stmt->fetch();

  if (!$student) {
    echo json_encode(['status' => 'not_found']);
    exit;
  }

  // Check for active (Flying) flight
  $check = $db->prepare("
    SELECT id FROM wp_flight_logs
    WHERE student_id = :id AND status = 'Flying'
    ORDER BY id DESC
    LIMIT 1
  ");
  $check->execute([':id' => $student['student_id']]);
  $active = $check->fetch();

  $studentInfo = [
    'student_id' => $student['student_id'],
    'first_name' => $student['first_name'],
    'last_name' => $student['last_name'],
    'email' => $student['email'],
    'phone' => $student['phone'],
    'type' => $student['type'],
    'home_airport' => $student['home_airport'],
    'aircraft_hours_remaining' => floatval($student['aircraft_hours_remaining']),
    'instructor_hours_remaining' => floatval($student['instructor_hours_remaining']),
    'phase_pay' => $student['phase_pay'],
    'installment_price' => $student['installment_price'],
    'installment_flight_hours' => $student['installment_flight_hours'],
    'installment_instructor_hours' => $student['installment_instructor_hours'],
    'installments_remaining' => $student['installments_remaining'],
    'latest_contract_file' => $student['latest_contract_file'],
    'current_lesson' => $student['current_lesson'],
    'tsa_clearance' => $student['tsa_clearance'],
    'solo_endorsement' => $student['solo_endorsement'],
    'status' => $student['status']
  ];

if ($active && isset($active['id'])) {
  echo json_encode([
    'status' => 'already_checked_out',
    'student_id' => $student['student_id'],
    'checkout_id' => $active['id'],
    'first_name' => $student['first_name'],
    'last_name' => $student['last_name'],
    'phone' => $student['phone']
  ]);
  exit;
}

  // Flight eligibility logic
  $type = $student['type'];
  $aircraft = $studentInfo['aircraft_hours_remaining'];
  $instructor = $studentInfo['instructor_hours_remaining'];

  $aircraftMin = 0;
  $instructorMin = 0;
  $needsInstructor = false;
  $promptSolo = false;

  switch ($type) {
    case 'student-presolo':
      $aircraftMin = 1.5;
      $instructorMin = 1.5;
      $needsInstructor = true;
      break;
    case 'student-postsolo':
      $aircraftMin = 1.5;
      $promptSolo = true;
      break;
    case 'renter-piston grad':
      $aircraftMin = 1.0;
      break;
    case 'renter-standard':
      $aircraftMin = 1.5;
      break;
    case 'renter-no checkout':
      $aircraftMin = 1.5;
      $instructorMin = 1.5;
      $needsInstructor = true;
      break;
  }

  // Not enough hours?
  if ($aircraft < $aircraftMin || ($needsInstructor && $instructor < $instructorMin)) {
    echo json_encode([
      'status' => 'not_enough_hours',
      'student' => $studentInfo,
      'required_aircraft' => $aircraftMin,
      'required_instructor' => $needsInstructor ? $instructorMin : 0
    ]);
    exit;
  }

  
  // ✅ Check if student has a flight scheduled today (Central Time)
  $date = (new DateTime('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');
  $schedCheck = $db->prepare("
    SELECT id FROM wp_flight_schedule
    WHERE student_id = :id
      AND DATE(start_time) = :today
      AND status = 'scheduled'
    LIMIT 1
  ");
  $schedCheck->execute([
    ':id' => $student['student_id'],
    ':today' => $date
  ]);
  $hasFlightToday = $schedCheck->fetchColumn();

  if (!$hasFlightToday) {
    echo json_encode([
      'status' => 'no_flight_today',
      'message' => 'No flight scheduled today. Please contact dispatch.'
    ]);
    exit;
  }

  // All good → allow checkout
  echo json_encode([
    'status' => 'ok',
    'student' => $studentInfo,
    'prompt_solo' => $promptSolo
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => $e->getMessage()
  ]);
}
