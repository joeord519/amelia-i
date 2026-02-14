<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/../calendar.v2/appointment-booking/send_email_notification.php');
require_once(__DIR__ . '/../calendar.v2/appointment-booking/google_calendar_push.php');
require_once(__DIR__ . '/../calendar.v2/calendar.v5/includes/aircraft_availability.php');

header('Content-Type: application/json');

try {
  $studentId = $_POST['student_id'] ?? null;
  $cfiId = $_POST['cfi_id'] ?? null;
  $tailNumber = $_POST['tail_number'] ?? null;
  $startTime = $_POST['start_time'] ?? null;
  $endTime = $_POST['end_time'] ?? null;

  if (!$studentId || !$tailNumber || !$startTime || !$endTime) {
    throw new Exception("Missing required fields.");
  }

  $db = getDB();

  $availabilityReason = '';
  if (!check_aircraft_available($db, $tailNumber, $startTime, $endTime, $availabilityReason)) {
    throw new Exception($availabilityReason);
  }
  $stmt = $db->prepare("INSERT INTO wp_flight_schedule (student_id, cfi_id, tail_number, start_time, end_time, status) VALUES (:sid, :cfi, :tail, :start, :end, 'Scheduled')");
  $stmt->execute([
    ':sid' => $studentId,
    ':cfi' => $cfiId ?: null,
    ':tail' => $tailNumber,
    ':start' => $startTime,
    ':end' => $endTime
  ]);

  $inserted_flight_id = $db->lastInsertId();

// Lookup student details
$studentStmt = $db->prepare("SELECT first_name, last_name, email, phone FROM wp_students WHERE student_id = ?");
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
$studentEmail = $student['email'] ?? '';
$studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$studentPhone = $student['phone'] ?? '';

// Lookup CFI name & phone
$cfiName = '';
$cfiPhone = '';
if ($cfiId) {
  $cfiStmt = $db->prepare("SELECT first_name, last_name, phone FROM wp_cfis WHERE cfi_id = ?");
  $cfiStmt->execute([$cfiId]);
  $cfi = $cfiStmt->fetch(PDO::FETCH_ASSOC);
  $cfiName = trim(($cfi['first_name'] ?? '') . ' ' . ($cfi['last_name'] ?? ''));
  $cfiPhone = $cfi['phone'] ?? '';
}

// Push to Google Calendar
$flightData = [
  'cfiId' => $cfiId,
  'cfiName' => $cfiName,
  'flightTypeId' => null,
  'flightTypeName' => 'Dual Training', // hardcoded or dynamic if needed
  'date' => date('Y-m-d', strtotime($startTime)),
  'time' => date('H:i', strtotime($startTime)),
  'phone' => $studentPhone,
  'aircraftId' => $tailNumber
];

$calendarEventId = pushToGoogleCalendar($db, $flightData);
if ($calendarEventId && is_string($calendarEventId)) {
  $stmt = $db->prepare("UPDATE wp_flight_schedule SET google_event_id = ? WHERE id = ?");
  $stmt->execute([$calendarEventId, $inserted_flight_id]);
}

// Send Email Notification
if ($studentEmail) {
  sendEmailNotification($studentEmail, [
    'flight_type' => 'Dual Training',
    'student_name' => $studentName,
    'student_phone' => $studentPhone,
    'tail_number' => $tailNumber,
    'cfi_name' => $cfiName,
    'cfi_phone' => $cfiPhone,
    'start' => $startTime,
    'end' => $endTime
  ]);
}

  echo json_encode(['status' => 'success', 'message' => 'Flight booked']);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
