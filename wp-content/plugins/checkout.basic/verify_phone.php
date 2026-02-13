<?php
// /wp-content/plugins/checkout.basic/verify_phone.php

require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

// ---- Debug toggle (set to true temporarily if needed) ----
$DEBUG = false;
if ($DEBUG) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  file_put_contents(__DIR__ . '/debug_post.txt', print_r($_POST, true));
}

// Helper: JSON response + exit
function respond($arr, $code = 200) {
  http_response_code($code);
  echo json_encode($arr);
  exit;
}

try {
  $db = getDB();

  // 1) Sanitize phone input
  $phoneRaw = $_POST['phone'] ?? '';
  $clean = preg_replace('/\D/', '', $phoneRaw);

  if (strlen($clean) !== 10) {
    respond(['status' => 'error', 'message' => 'Invalid phone format.'], 400);
  }

  $formattedPhone = '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6);

  // 2) Look up student (explicit columns for stability)
  $stmt = $db->prepare("
    SELECT
      student_id,
      first_name,
      last_name,
      email,
      phone,
      type,
      home_airport,
      aircraft_hours_remaining,
      instructor_hours_remaining,
      phase_pay,
      installment_price,
      installment_flight_hours,
      installment_instructor_hours,
      installments_remaining,
      latest_contract_file,
      latest_contract_signed,
      latest_contract_signed_at,
      current_lesson,
      tsa_clearance,
      solo_endorsement,
      status
    FROM wp_students
    WHERE phone = :p
    LIMIT 1
  ");
  $stmt->execute([':p' => $formattedPhone]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$student) {
    respond(['status' => 'not_found']);
  }

  // Normalize contract flag (0/1)
  $student['latest_contract_signed'] = intval($student['latest_contract_signed'] ?? 0);

  // Build studentInfo payload (used by multiple statuses)
  $studentInfo = [
    'student_id' => intval($student['student_id']),
    'first_name' => $student['first_name'],
    'last_name'  => $student['last_name'],
    'email'      => $student['email'],
    'phone'      => $student['phone'],
    'type'       => $student['type'],
    'home_airport' => $student['home_airport'],

    'aircraft_hours_remaining'    => floatval($student['aircraft_hours_remaining']),
    'instructor_hours_remaining'  => floatval($student['instructor_hours_remaining']),
    'phase_pay' => $student['phase_pay'],
    'installment_price' => $student['installment_price'],
    'installment_flight_hours' => $student['installment_flight_hours'],
    'installment_instructor_hours' => $student['installment_instructor_hours'],
    'installments_remaining' => $student['installments_remaining'],

    'latest_contract_file' => $student['latest_contract_file'],

    // ✅ New enforcement fields
    'latest_contract_signed'    => intval($student['latest_contract_signed']),
    'latest_contract_signed_at' => $student['latest_contract_signed_at'] ?? null,

    'current_lesson' => $student['current_lesson'],
    'tsa_clearance' => $student['tsa_clearance'],
    'solo_endorsement' => $student['solo_endorsement'],
    'status' => $student['status']
  ];

  // 3) Check for active (Flying) flight (already checked out)
  $check = $db->prepare("
    SELECT id
    FROM wp_flight_logs
    WHERE student_id = :id AND status = 'Flying'
    ORDER BY id DESC
    LIMIT 1
  ");
  $check->execute([':id' => $studentInfo['student_id']]);
  $active = $check->fetch(PDO::FETCH_ASSOC);

  if ($active && isset($active['id'])) {
    // ✅ Include contract flag + minimal identity fields so checkout.php can redirect to agreement
    respond([
      'status' => 'already_checked_out',
      'student_id' => $studentInfo['student_id'],
      'checkout_id' => intval($active['id']),
      'first_name' => $studentInfo['first_name'],
      'last_name'  => $studentInfo['last_name'],
      'phone'      => $studentInfo['phone'],
      'email'      => $studentInfo['email'],
      'latest_contract_signed' => $studentInfo['latest_contract_signed']
    ]);
  }

  // 4) Flight eligibility logic (hours)
  $type = $studentInfo['type'];
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
    respond([
      'status' => 'not_enough_hours',
      'student' => $studentInfo,
      'required_aircraft' => $aircraftMin,
      'required_instructor' => $needsInstructor ? $instructorMin : 0
    ]);
  }

  // 5) Must have a flight scheduled today (Central Time)
  $date = (new DateTime('now', new DateTimeZone('America/Chicago')))->format('Y-m-d');

  $schedCheck = $db->prepare("
    SELECT id
    FROM wp_flight_schedule
    WHERE student_id = :id
      AND DATE(start_time) = :today
      AND status = 'scheduled'
    LIMIT 1
  ");
  $schedCheck->execute([
    ':id' => $studentInfo['student_id'],
    ':today' => $date
  ]);

  $hasFlightToday = $schedCheck->fetchColumn();

  if (!$hasFlightToday) {
    respond([
      'status' => 'no_flight_today',
      'message' => 'No flight scheduled today. Please contact dispatch.'
    ]);
  }

  // 6) All good → allow checkout
  respond([
    'status' => 'ok',
    'student' => $studentInfo,
    'prompt_solo' => $promptSolo
  ]);

} catch (Exception $e) {
  respond([
    'status' => 'error',
    'message' => $e->getMessage()
  ], 500);
}

