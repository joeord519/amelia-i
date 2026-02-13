<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/sync_google_calendar.php');

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['title']) || empty($data['date']) || empty($data['time']) || empty($data['duration'])) {
  echo json_encode(['success' => false, 'message' => 'Missing required fields']);
  exit;
}

$title    = $data['title'];
$start    = $data['date'] . ' ' . $data['time'] . ':00';
$duration = (int) $data['duration'];
$end      = date('Y-m-d H:i:s', strtotime($start) + $duration * 60);

$cfis     = $data['cfis'] ?? [];
$aircraft = $data['aircraft'] ?? [];
$created  = [];

try {
  $conn = getDB();
  $conn->beginTransaction();

  foreach (array_merge($cfis, $aircraft) as $resourceId) {
    $isCFI     = in_array($resourceId, $cfis);
    $isAircraft = in_array($resourceId, $aircraft);

    $stmt = $conn->prepare("INSERT INTO wp_flight_schedule 
      (flight_type, event_title, student_id, cfi_id, tail_number, start_time, end_time, home_airport)
      VALUES ('Company Event', :event_title, NULL, :cfi, :tail, :start, :end, 'Company')");

    $stmt->execute([
      ':event_title' => $title,
      ':cfi'   => $isCFI ? $resourceId : null,
      ':tail'  => $isAircraft ? $resourceId : null,
      ':start' => $start,
      ':end'   => $end
    ]);

    $flightId = $conn->lastInsertId();

    $created[] = [
      'id' => 'company-' . $flightId,
      'title' => $title,
      'start' => $start,
      'end' => $end,
      'resourceId' => $resourceId,
      'backgroundColor' => '#007BFF',
      'borderColor' => '#007BFF',
      'textColor' => '#ffffff',
      'extendedProps' => [
        'flight_id' => $flightId,
        'flight_type' => 'Company Event',
        'event_title' => $title
      ]
    ];
  }

  $conn->commit();

  echo json_encode(['success' => true, 'events' => $created]);
  flush();
  if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

  foreach ($cfis as $cfi_id) {
    $cfiStmt = $conn->prepare("SELECT first_name, last_name, email FROM wp_cfis WHERE cfi_id = ?");
    $cfiStmt->execute([$cfi_id]);
    $cfi = $cfiStmt->fetch(PDO::FETCH_ASSOC);

    if ($cfi && !empty($cfi['email'])) {
      $fullName = trim("{$cfi['first_name']} {$cfi['last_name']}");
      mail(
        $cfi['email'],
        "📅 Company Event Scheduled",
        "Hi {$fullName},\n\nYou've been added to a company event:\n\nTitle: $title\nStart: $start\nDuration: {$duration} min\n\n- Piston Team",
        "From: no-reply@pistonaviation.com"
      );
    }

    foreach ($created as $ev) {
      if ((string)$ev['resourceId'] === (string)$cfi_id) {
        $flight = [
          'id' => $ev['extendedProps']['flight_id'],
          'cfi_id' => $cfi_id,
          'start_time' => $ev['start'],
          'end_time' => $ev['end'],
          'flight_type' => 'Company Event',
          'event_title' => $title,
          'future_student_name' => '',
          'future_student_phone' => ''
        ];
        createGoogleCalendarEvent($flight);
      }
    }
  }

} catch (Exception $e) {
  $conn->rollBack();
  http_response_code(500);
  echo "<pre>Exception: " . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>";
  exit;
}

