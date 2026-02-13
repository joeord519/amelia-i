<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents("php://input"), true);
  $phone = trim($input['phone'] ?? '');

  if (!$phone || strlen($phone) < 10) {
    throw new Exception("Invalid phone number.");
  }

  $db = getDB();
  $cleanPhone = preg_replace('/\D+/', '', $phone);

  $stmt = $db->prepare("SELECT id FROM wp_students WHERE REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', '') = ?");
  $stmt->execute([$cleanPhone]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);
  $studentId = $student['id'] ?? null;

  $stmt = $db->query("SELECT * FROM wp_flight_schedule WHERE status != 'Canceled'");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $events = [];
  foreach ($rows as $row) {
    $isStudentEvent = (
      $row['student_id'] == $studentId ||
      $row['future_student_phone'] === $phone ||
      $row['ground_student_1'] == $studentId ||
      $row['ground_student_2'] == $studentId ||
      $row['ground_student_3'] == $studentId ||
      $row['ground_student_4'] == $studentId
    );

    // Generate 2 events per row if both aircraft and CFI are set
    if (!empty($row['tail_number'])) {
      $events[] = [
        'id' => 'ac-' . $row['id'],
        'title' => $row['event_title'] ?? $row['flight_type'],
        'start' => date('c', strtotime($row['start_time'])),
        'end' => date('c', strtotime($row['end_time'])),
        'resourceId' => $row['tail_number'],
        'editable' => false,
        'backgroundColor' => $isStudentEvent ? '#3b82f6' : '#e5e7eb',
        'textColor' => $isStudentEvent ? '#ffffff' : '#000000',
        'extendedProps' => [ 'isStudentEvent' => $isStudentEvent ]
      ];
    }

    if (!empty($row['cfi_id'])) {
      $events[] = [
        'id' => 'cfi-' . $row['id'],
        'title' => $row['event_title'] ?? $row['flight_type'],
        'start' => date('c', strtotime($row['start_time'])),
        'end' => date('c', strtotime($row['end_time'])),
        'resourceId' => $row['cfi_id'],
        'editable' => false,
        'backgroundColor' => $isStudentEvent ? '#3b82f6' : '#e5e7eb',
        'textColor' => $isStudentEvent ? '#ffffff' : '#000000',
        'extendedProps' => [ 'isStudentEvent' => $isStudentEvent ]
      ];
    }
  }

  echo json_encode(['events' => $events]);

} catch (Exception $e) {
  echo json_encode(['events' => [], 'error' => $e->getMessage()]);
}

