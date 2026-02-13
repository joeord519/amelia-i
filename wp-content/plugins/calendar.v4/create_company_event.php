<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/push_google_event.php'); // Make sure this path is correct

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents("php://input"), true);

  $title     = $input['title'] ?? '';
  $date      = $input['date'] ?? '';
  $time      = $input['time'] ?? '';
  $duration  = intval($input['duration'] ?? 60);
  $cfis      = $input['cfis'] ?? [];
  $aircrafts = $input['aircraft'] ?? [];

  if (!$title || !$date || !$time || $duration <= 0) {
    throw new Exception("Missing required fields.");
  }

  $startTime = new DateTime("$date $time");
  $endTime   = clone $startTime;
  $endTime->modify("+$duration minutes");

  $db = getDB();
  $insertedEvents = [];

  // Insert for CFIs
  foreach ($cfis as $cfiId) {
    $stmt = $db->prepare("
      INSERT INTO wp_flight_schedule (
        flight_type, cfi_id, student_id,
        start_time, end_time, time_slot_duration, status, event_title
      ) VALUES (
        'Company Event', :cfi_id, NULL,
        :start_time, :end_time, :duration, 'Scheduled', :event_title
      )
    ");

    $stmt->execute([
      ':cfi_id'      => $cfiId,
      ':start_time'  => $startTime->format('Y-m-d H:i:s'),
      ':end_time'    => $endTime->format('Y-m-d H:i:s'),
      ':duration'    => $duration,
      ':event_title' => $title
    ]);

    $eventId = $db->lastInsertId();

    // Push to Google Calendar (optional function)
    pushGoogleEvent([
      'flight_id' => $eventId,
      'cfi_id' => $cfiId,
      'title' => "Company Event – $title",
      'start' => $startTime->format('Y-m-d H:i:s'),
      'end' => $endTime->format('Y-m-d H:i:s'),
      'is_company_event' => true
    ]);

   // Fetch CFI email
$cfiEmailStmt = $db->prepare("SELECT email, first_name FROM wp_cfis WHERE cfi_id = ?");
$cfiEmailStmt->execute([$cfiId]);
$cfiInfo = $cfiEmailStmt->fetch(PDO::FETCH_ASSOC);

if ($cfiInfo && !empty($cfiInfo['email'])) {
  $to = $cfiInfo['email'];
  $subject = "📅 New Company Event: $title";
  $body = "Hi {$cfiInfo['first_name']},\n\nYou've been added to a new company event:\n\nEvent: $title\nDate: $date\nTime: $time\nDuration: $duration minutes\n\nThis event has also been added to your Google Calendar.";

  // Send email (via PHP mail or Mailgun if preferred)
  mail($to, $subject, $body, "From: no-reply@flypiston.com");
}



    $insertedEvents[] = [
  'id' => uniqid("ce-ac-"),
  'title' => "Aircraft: $title",
  'start' => (clone $startTime)->format('Y-m-d\TH:i:s'),
  'end' => (clone $endTime)->format('Y-m-d\TH:i:s'),
  'resourceId' => $tailNumber,
  'extendedProps' => [ "flight_type" => "Company Event" ]
];

  }

  // Insert for Aircraft (no Google push)
  foreach ($aircrafts as $tailNumber) {
    $stmt = $db->prepare("
      INSERT INTO wp_flight_schedule (
        flight_type, tail_number, student_id,
        start_time, end_time, time_slot_duration, status, event_title
      ) VALUES (
        'Company Event', :tail_number, NULL,
        :start_time, :end_time, :duration, 'Scheduled', :event_title
      )
    ");

    $stmt->execute([
      ':tail_number' => $tailNumber,
      ':start_time'  => $startTime->format('Y-m-d H:i:s'),
      ':end_time'    => $endTime->format('Y-m-d H:i:s'),
      ':duration'    => $duration,
      ':event_title' => $title
    ]);

    $insertedEvents[] = [
  'id' => uniqid("ce-ac-"),
  'title' => "Aircraft: $title",
  'start' => (clone $startTime)->format('Y-m-d\TH:i:s'),
  'end' => (clone $endTime)->format('Y-m-d\TH:i:s'),
  'resourceId' => $tailNumber,
  'extendedProps' => [ "flight_type" => "Company Event" ]
];

  }

  echo json_encode([
    'success' => true,
    'events' => $insertedEvents
  ]);
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'message' => $e->getMessage()
  ]);
}




