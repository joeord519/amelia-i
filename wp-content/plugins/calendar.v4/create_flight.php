<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

$logPath = __DIR__ . '/debug_create_flight.log';
file_put_contents($logPath, "\n=== FLIGHT CREATE START ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

try {
  $input = json_decode(file_get_contents('php://input'), true);
file_put_contents(__DIR__ . '/debug_create_flight.log', print_r($input, true), FILE_APPEND);
  file_put_contents($logPath, "Input:\n" . print_r($input, true), FILE_APPEND);
  if (!$input) throw new Exception("Invalid input");

  $db = getDB();
  $start = "{$input['date']} {$input['time']}:00";

  // Fetch duration + flight type name
  $stmt = $db->prepare("SELECT default_duration, name FROM wp_flight_types WHERE id = ?");
  $stmt->execute([$input['flightTypeId']]);
  $flightTypeData = $stmt->fetch(PDO::FETCH_ASSOC);

  $duration = $flightTypeData['default_duration'] ?? 90;
  $flightType = $flightTypeData['name'] ?? 'Flight';
  $end = date("Y-m-d H:i:s", strtotime("+$duration minutes", strtotime($start)));
  $isSolo = str_contains(strtolower($flightType), 'solo');
$studentId = $input['studentId'] ?? null;
file_put_contents(__DIR__ . '/debug_create_flight.log', print_r($input, true), FILE_APPEND);
if (!$studentId) {
  file_put_contents(__DIR__ . '/debug_create_flight.log', "❌ studentId missing\n", FILE_APPEND);
  throw new Exception("Missing studentId");
}

  // Insert flight
  $stmt = $db->prepare("
    INSERT INTO wp_flight_schedule (flight_type_id, student_id, start_time, end_time, cfi_id, tail_number, status)
    VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')
  ");
  $stmt->execute([
    $input['flightTypeId'],
    $studentId,
    $start,
    $end,
    $isSolo ? null : $input['cfiId'], // null if solo
    $input['tailNumber']
  ]);
  $flightId = $db->lastInsertId();
  file_put_contents($logPath, "Flight ID: $flightId\n", FILE_APPEND);

  // Get student info
  $studentName = $input['futureStudentName'] ?? '';
  $studentPhone = $input['futureStudentPhone'] ?? '';
  $studentEmail = '';

  if (!empty($input['studentId'])) {
    $stmt = $db->prepare("INSERT INTO wp_flight_students (flight_id, student_id) VALUES (?, ?)");
    $stmt->execute([$flightId, $input['studentId']]);

    if (!$studentName) {
      $stmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name, phone, email FROM wp_students WHERE student_id = ?");
      $stmt->execute([$input['studentId']]);
      $s = $stmt->fetch();
      $studentName = $s['name'];
      $studentPhone = $s['phone'];
      $studentEmail = $s['email'] ?? '';
    }
  }

  // Get aircraft color
  $stmt = $db->prepare("SELECT label_color FROM wp_aircraft WHERE tail_number = ?");
  $stmt->execute([$input['tailNumber']]);
  $aircraft = $stmt->fetch();
  $labelColor = $aircraft['label_color'] ?? '#007BFF';

  $tailNumber = $input['tailNumber'];
  $cfiName = '';
  $cfiPhone = '';
  $cfiId = null;

  if (!$isSolo) {
    $stmt = $db->prepare("SELECT first_name, last_name, phone FROM wp_cfis WHERE cfi_id = ?");
    $stmt->execute([$input['cfiId']]);
    $c = $stmt->fetch();
    $cfiName = $c ? $c['first_name'] . ' ' . $c['last_name'] : 'Unknown';
    $cfiPhone = $c['phone'] ?? '';
    $cfiId = $input['cfiId'];
  }

  $eventTitle = "$flightType - $studentName - $tailNumber";

  // Email
  if (!empty($studentEmail)) {
    sendEmailNotification($studentEmail, [
      'start' => $start,
      'end' => $end,
      'flight_type' => $flightType,
      'student_name' => $studentName,
      'student_phone' => $studentPhone,
      'tail_number' => $tailNumber,
      'cfi_name' => $cfiName,
      'cfi_phone' => $cfiPhone
    ]);
  }

  // Google Calendar
  createGoogleCalendarEvent([
    'id' => $flightId,
    'start_time' => $start,
    'end_time' => $end,
    'cfi_id' => $cfiId,
    'flight_type' => $flightType,
    'event_title' => $eventTitle,
    'student_name' => $studentName,
    'tail_number' => $tailNumber,
    'cfi_name' => $cfiName,
    'cfi_phone' => $cfiPhone
  ]);

  // Build event payloads
  $events = [];

  // Aircraft row (always)
  $events[] = [
    'id' => $flightId . '-ac',
    'title' => $eventTitle,
    'start' => $start,
    'end' => $end,
    'resourceId' => $tailNumber,
    'backgroundColor' => $labelColor,
    'borderColor' => $labelColor,
    'textColor' => '#ffffff',
    'extendedProps' => [
      'flight_id' => $flightId,
      'flight_type' => $flightType,
      'student_name' => $studentName,
      'student_phone' => $studentPhone,
      'tail_number' => $tailNumber,
      'cfi_id' => $cfiId,
      'cfi_name' => $cfiName
    ]
  ];

  // CFI row (only if not solo)
  if (!$isSolo) {
    $events[] = [
      'id' => $flightId,
      'title' => $eventTitle,
      'start' => $start,
      'end' => $end,
      'resourceId' => $cfiId,
      'backgroundColor' => $labelColor,
      'borderColor' => $labelColor,
      'textColor' => '#ffffff',
      'extendedProps' => [
        'flight_id' => $flightId,
        'flight_type' => $flightType,
        'student_name' => $studentName,
        'student_phone' => $studentPhone,
        'tail_number' => $tailNumber,
        'cfi_id' => $cfiId,
        'cfi_name' => $cfiName
      ]
    ];
  }

  echo json_encode([
    'success' => true,
    'events' => $events
  ]);

} catch (Exception $e) {
  file_put_contents($logPath, "❌ ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ✅ FIXED EMAIL NOTIFICATION LOGIC — DIRECT FUNCTION CALLS
require_once(__DIR__ . '/send_email_notification.php');

$studentIds = array_map(function($s) {
  return $s['id'];
}, $input['students'] ?? []);

if (!empty($studentIds)) {
  $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
  $stmtS = $db->prepare("SELECT student_id, CONCAT(first_name, ' ', last_name) AS name, email, phone FROM wp_students WHERE student_id IN ($placeholders)");
  $stmtS->execute($studentIds);
  $students = $stmtS->fetchAll(PDO::FETCH_ASSOC);

  // Get CFI Info
  $stmtCfi = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name, phone FROM wp_cfis WHERE cfi_id = ?");
  $stmtCfi->execute([$input['cfiId']]);
  $cfi = $stmtCfi->fetch();
  $cfiName = $cfi['name'] ?? 'Your Instructor';
  $cfiPhone = $cfi['phone'] ?? '';

  foreach ($students as $s) {
    $classmates = array_filter(array_map(function($peer) use ($s) {
      return $peer['student_id'] !== $s['student_id'] ? $peer['name'] : null;
    }, $students));

    $classmateList = implode('<br>', array_values($classmates));

    $start = "{$input['date']} {$input['time']}";
    $end = date('Y-m-d H:i:s', strtotime($start) + ($input['duration'] * 60));

    $calendarLink = "https://calendar.google.com/calendar/render?action=TEMPLATE&text=Ground+Lesson+with+" . urlencode($cfiName) .
      "&dates=" . date('Ymd\THis', strtotime($start)) . "/" . date('Ymd\THis', strtotime($end)) .
      "&details=Location:+Piston+Aviation";

    if (!empty($s['email'])) {
      sendEmailNotification($s['email'], [
        'flight_type' => 'Ground Lesson',
        'student_name' => $s['name'],
        'student_phone' => $s['phone'],
        'cfi_name' => $cfiName,
        'cfi_phone' => $cfiPhone,
        'start' => $start,
        'end' => $end,
        'classmates' => $classmateList,
        'google_calendar_link' => $calendarLink
      ]);
    }
  }
}