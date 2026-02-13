<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!$input) throw new Exception("Invalid input");

  $conn = getDB();

  $start = "{$input['date']} {$input['time']}:00";
  $end = date("Y-m-d H:i:s", strtotime("+90 minutes", strtotime($start))); // Default to 1.5hr flights

  $stmt = $conn->prepare("
    INSERT INTO wp_flight_schedule 
    (flight_type_id, start_time, end_time, cfi_id, tail_number, location_code, status)
    VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')
  ");
  $stmt->execute([
    $input['flightTypeId'],
    $start,
    $end,
    $input['cfiId'],
    $input['tailNumber'],
    $input['home_airport']
  ]);
  $flightId = $conn->lastInsertId();

  // Add student mapping
  if (!empty($input['studentId'])) {
    $stmt = $conn->prepare("INSERT INTO wp_flight_students (flight_id, student_id) VALUES (?, ?)");
    $stmt->execute([$flightId, $input['studentId']]);
  }

  // Fetch CFI name
  $stmt = $conn->prepare("SELECT first_name, last_name FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$input['cfiId']]);
  $cfi = $stmt->fetch();
  $cfiName = $cfi ? $cfi['first_name'] . ' ' . $cfi['last_name'] : 'N/A';

  // Student Info (either future or current)
  $studentName = !empty($input['futureName']) ? $input['futureName'] : '';
  $studentPhone = !empty($input['futurePhone']) ? $input['futurePhone'] : '';

  if (!$studentName && $input['studentId']) {
    $stmt = $conn->prepare("SELECT first_name, last_name, phone FROM wp_students WHERE student_id = ?");
    $stmt->execute([$input['studentId']]);
    $student = $stmt->fetch();
    if ($student) {
      $studentName = $student['first_name'] . ' ' . $student['last_name'];
      $studentPhone = $student['phone'];
    }
  }

  // Email notification
  if (!empty($studentPhone)) {
    $payload = json_encode([
      'to' => $studentPhone,
      'subject' => "✈️ Flight Scheduled",
      'body' => "Hi {$studentName},<br><br>Your flight has been scheduled on <strong>{$input['date']}</strong> at <strong>{$input['time']}</strong> with <strong>{$cfiName}</strong>.<br><br>Tail #: {$input['tailNumber']}<br><br>- Piston Aviation"
    ]);
    $opts = [
      'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => $payload
      ]
    ];
    file_get_contents("https://amelia-i.com/wp-content/plugins/calendar.v3/send_email_notification.php", false, stream_context_create($opts));
  }

  // Push to Google Calendar
  $calendarFlight = [
    'id' => $flightId,
    'start_time' => $start,
    'end_time' => $end,
    'cfi_id' => $input['cfiId'],
    'flight_type' => 'Flight',
    'flight_type_name' => 'Flight'
  ];
  createGoogleCalendarEvent($calendarFlight);

  echo json_encode([
    'success' => true,
    'events' => [[
      'id' => $flightId,
      'title' => "Flight - {$studentName}",
      'start' => $start,
      'end' => $end,
      'resourceId' => $input['cfiId'],
      'backgroundColor' => '#007BFF',
      'borderColor' => '#007BFF',
      'textColor' => '#ffffff',
      'extendedProps' => [
        'student_name' => $studentName,
        'student_phone' => $studentPhone,
        'tail_number' => $input['tailNumber'],
        'cfi_name' => $cfiName,
        'flight_id' => $flightId
      ]
    ]]
  ]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
