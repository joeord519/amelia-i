<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents('php://input'), true);

  if (!$data || !isset($data['date'], $data['time'], $data['duration'], $data['cfiId'], $data['students'])) {
    throw new Exception("Missing required data.");
  }

  $conn = getDB();

  $start = "{$data['date']} {$data['time']}:00";
  $duration = intval($data['duration']);
  $end = date("Y-m-d H:i:s", strtotime("+$duration minutes", strtotime($start)));
  $cfiId = $data['cfiId'];
  $students = $data['students'];

  $stmt = $conn->prepare("
    INSERT INTO wp_flight_schedule (flight_type, start_time, end_time, cfi_id, status)
    VALUES ('Ground Lesson', ?, ?, ?, 'Scheduled')
  ");
  $stmt->execute([$start, $end, $cfiId]);
  $flightId = $conn->lastInsertId();

  foreach ($students as $s) {
    $stmt = $conn->prepare("INSERT INTO wp_flight_students (flight_id, student_id) VALUES (?, ?)");
    $stmt->execute([$flightId, $s['id']]);
  }

  foreach ($students as $s) {
    $payload = json_encode([
      'to' => $s['phone'],
      'subject' => "📘 Ground Lesson Scheduled",
      'body' => "Hi {$s['name']},<br><br>You're scheduled for a ground lesson on <strong>{$data['date']}</strong> at <strong>{$data['time']}</strong> for {$duration} minutes.<br><br>See you there!<br>- Piston Aviation"
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

  // Google Calendar Push
  $googleFlight = [
  'id' => $flightId,
  'start_time' => $start,
  'end_time' => $end,
  'cfi_id' => $cfiId,
  'flight_type' => 'Ground Lesson',
  'flight_type_name' => 'Ground Lesson',
  'event_title' => 'Ground Lesson'
];

createGoogleCalendarEvent($googleFlight);


  echo json_encode([
    'success' => true,
    'events' => [[
      'id' => $flightId,
      'title' => "Ground Lesson",
      'start' => $start,
      'end' => $end,
      'resourceId' => $cfiId,
      'backgroundColor' => '#2563eb',
      'borderColor' => '#2563eb',
      'textColor' => '#ffffff',
      'extendedProps' => [ 'students' => $students ]
    ]]
  ]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => "Server error: " . $e->getMessage()]);
}

