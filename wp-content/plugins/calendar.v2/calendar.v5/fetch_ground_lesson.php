<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  $db = getDB();
  $db->exec("SET time_zone = 'America/Chicago'");

  // Get all Ground Lessons from flight_schedule
  $stmt = $db->query("
    SELECT 
      f.id AS flight_id,
      f.start_time,
      f.end_time,
      f.cfi_id,
      c.first_name AS cfi_first,
      c.last_name AS cfi_last
    FROM wp_flight_schedule f
    LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
    WHERE f.flight_type = 'Ground Lesson'
    ORDER BY f.start_time ASC
  ");

  $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $events = [];

  foreach ($lessons as $lesson) {
    $flightId = $lesson['flight_id'];
    $cfiName = trim("{$lesson['cfi_first']} {$lesson['cfi_last']}");

    // Get all students for this lesson
    $stmt2 = $db->prepare("
      SELECT s.student_id, CONCAT(s.first_name, ' ', s.last_name) AS name, s.phone
      FROM wp_flight_students fs
      LEFT JOIN wp_students s ON fs.student_id = s.student_id
      WHERE fs.flight_id = ?
    ");
    $stmt2->execute([$flightId]);
    $students = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $events[] = [
      'id' => $flightId,
      'title' => 'Ground Lesson x ' . count($students),
      'start' => $lesson['start_time'],
      'end' => $lesson['end_time'],
      'resourceId' => $lesson['cfi_id'],
      'backgroundColor' => '#22c55e',
      'borderColor' => '#22c55e',
      'textColor' => '#ffffff',
      'extendedProps' => [
        'flight_id' => $flightId,
        'flight_type' => 'Ground Lesson',
        'cfi_id' => $lesson['cfi_id'],
        'cfi_name' => $cfiName,
        'students' => $students
      ]
    ];
  }

  echo json_encode(['events' => $events]);

} catch (Exception $e) {
  echo json_encode(['events' => [], 'error' => $e->getMessage()]);
}
