<?php

file_put_contents(__DIR__ . "/logs/lesson_loaded.txt", "✅ create_ground_lesson.php loaded\n", FILE_APPEND);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function () {
  $error = error_get_last();
  if ($error !== null) {
    file_put_contents(__DIR__ . "/logs/fatal_shutdown_log.txt", "💥 FATAL ERROR: " . print_r($error, true), FILE_APPEND);
    header("Content-Type: application/json");
    echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $error['message']]);
    exit;
  }
});

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/send_email_notification.php');
require_once(__DIR__ . '/sync_google_calendar.php');
require_once(__DIR__ . '/send_ground_email.php');

header('Content-Type: application/json');

$logPath = __DIR__ . '/logs/debug_create_flight.log';
file_put_contents($logPath, "\n=== FLIGHT CREATE START ===\n" . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

$data = json_decode(file_get_contents("php://input"), true);
file_put_contents($logPath, "📦 Incoming Data: " . print_r($data, true), FILE_APPEND);

if (!$data || !isset($data['cfiId'], $data['date'], $data['time'], $data['duration'], $data['students'])) {
  echo json_encode(['success' => false, 'message' => 'Missing required fields']);
  exit;
}

try {
  $db = getDB();

  $start = $data['date'] . ' ' . $data['time'];
  $duration = (int)$data['duration'];
  $end = date("Y-m-d H:i:s", strtotime("$start +$duration minutes"));

  $studentIds = array_column($data['students'], 'id');
  $studentNames = array_column($data['students'], 'name');

  $stmt = $db->prepare("INSERT INTO wp_flight_schedule 
    (flight_type, cfi_id, ground_student_1, ground_student_2, ground_student_3, ground_student_4, start_time, end_time, created_at)
    VALUES (:type, :cfi, :s1, :s2, :s3, :s4, :start, :end, NOW())");

  $stmt->bindValue(':type', 'Ground Lesson');
  $stmt->bindValue(':cfi', $data['cfiId']);
  $stmt->bindValue(':s1', $studentIds[0] ?? null);
  $stmt->bindValue(':s2', $studentIds[1] ?? null);
  $stmt->bindValue(':s3', $studentIds[2] ?? null);
  $stmt->bindValue(':s4', $studentIds[3] ?? null);
  $stmt->bindValue(':start', $start);
  $stmt->bindValue(':end', $end);

  if (!$stmt->execute()) {
    file_put_contents($logPath, "\n🚨 SQL ERROR: " . print_r($stmt->errorInfo(), true), FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'DB insert failed']);
    exit;
  }

  $flightId = $db->lastInsertId();
  file_put_contents($logPath, "\n✅ INSERT SUCCESS - ID: $flightId\n", FILE_APPEND);

  $cfiName = $data['cfiName'] ?? 'TBD';
  $cfiPhone = $data['cfiPhone'] ?? 'N/A';
  $classmateList = implode(', ', array_filter(array_column($data['students'], 'name')));
  $classmateList = implode(', ', array_filter(array_column($data['students'], 'name')));
  $tailNumber = 'N/A';
  $flightType = 'Ground Lesson';

  // Google Calendar push
  $calendarResponse = createGoogleCalendarEvent([
    'id' => $flightId,
    'flight_type' => $flightType,
    'start_time' => $start,
    'end_time' => $end,
    'cfi_id' => $data['cfiId'],
    'student_names' => implode(', ', $studentNames),
    'students' => $data['students']
  ]);

  // ✅ Filter students with valid email
  $validStudents = array_filter($data['students'], function ($s) {
    return isset($s['email']) && filter_var($s['email'], FILTER_VALIDATE_EMAIL);
  });

  file_put_contents(__DIR__ . "/logs/email_loop_valid_students.txt", print_r($validStudents, true), FILE_APPEND);
  file_put_contents(__DIR__ . "/logs/valid_student_count.txt", count($validStudents) . "\n", FILE_APPEND);

  foreach ($validStudents as $s) {
    $studentEmail = $s['email'];
    $studentName = $s['name'] ?? '';
    $studentPhone = $s['phone'] ?? '';

    $payload = [
      'start' => $start,
      'end' => $end,
      'flight_type' => $flightType,
      'student_name' => $studentName,
      'student_phone' => $studentPhone,
      'tail_number' => $tailNumber,
      'cfi_name' => $cfiName,
      'cfi_phone' => $cfiPhone,
    'classmates' => $classmateList,
    'classmates' => $classmateList
    ];

    file_put_contents(__DIR__ . "/logs/final_payload_log.txt", print_r(['to' => $studentEmail, 'payload' => $payload], true), FILE_APPEND);

    sendGroundLessonEmail($studentEmail, $payload);
  }

  echo json_encode([
    'success' => true,
    'event' => [
      'id' => $flightId,
      'title' => 'Ground - ' . implode(', ', $studentNames),
      'start' => $start,
      'end' => $end,
      'resourceId' => $data['cfiId'],
      'backgroundColor' => '#22c55e',
      'borderColor' => '#22c55e',
      'textColor' => '#ffffff'
    ]
  ]);

} catch (Exception $e) {
  file_put_contents($logPath, "\n🔥 EXCEPTION: " . $e->getMessage(), FILE_APPEND);
  echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}