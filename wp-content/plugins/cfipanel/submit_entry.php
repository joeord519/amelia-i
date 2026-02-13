<?php
session_start();
header('Content-Type: application/json');
require_once(__DIR__ . '/db_connect.php');
$db = getDB();

$cfi_id = $_SESSION['cfi_id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

$type = trim($input['entry_type'] ?? '');
$passenger_name = trim($input['passenger_name'] ?? '');
$tail_number = trim($input['tail_number'] ?? '');
$flight_time = floatval($input['total_flight_time'] ?? 0);
$ground_time = floatval($input['ground_time'] ?? 0);
$notes = trim($input['notes'] ?? '');
$now = date('Y-m-d');

try {
  if ($type === 'SOF Time') {
    $stmt = $db->prepare("
      INSERT INTO wp_flight_logs (cfi_id, flight_category, ground_time, instructor_notes, flight_date)
      VALUES (:cfi_id, 'SOF Time', :ground_time, :notes, :flight_date)
    ");
    $stmt->execute([
      ':cfi_id' => $cfi_id,
      ':ground_time' => $ground_time,
      ':notes' => $notes,
      ':flight_date' => $now
    ]);
    echo json_encode(['status' => 'success', 'message' => 'SOF Time logged.']);
    exit;
  }

  if ($type === 'Ground Lesson') {
    // Accept either a single student or multiple
    $student_ids = [];

    if (!empty($input['student_id'])) {
      $student_ids[] = (int)$input['student_id'];
    } elseif (!empty($input['student_ids']) && is_array($input['student_ids'])) {
      $student_ids = array_map('intval', $input['student_ids']);
    }

    if (empty($student_ids)) {
      echo json_encode(['status' => 'error', 'message' => 'No students selected.']);
      exit;
    }

    $shared_session_id = uniqid('GL_');
    $multiplier = (count($student_ids) === 1) ? 1 : 0.5;

    foreach ($student_ids as $sid) {
      $stmt = $db->prepare("
        INSERT INTO wp_flight_logs (
          cfi_id,
          student_id,
          flight_category,
          ground_time,
          instructor_notes,
          flight_date,
          shared_session_id
        )
        VALUES (
          :cfi_id,
          :student_id,
          'Ground Lesson',
          :ground_time,
          :notes,
          :flight_date,
          :shared_session_id
        )
      ");
      $stmt->execute([
        ':cfi_id' => $cfi_id,
        ':student_id' => $sid,
        ':ground_time' => $ground_time,
        ':notes' => $notes,
        ':flight_date' => $now,
        ':shared_session_id' => $shared_session_id
      ]);

      $deduct = $ground_time * $multiplier;
      $update = $db->prepare("
        UPDATE wp_students
        SET instructor_hours_remaining = instructor_hours_remaining - :deduct
        WHERE student_id = :sid
      ");
      $update->execute([
        ':deduct' => $deduct,
        ':sid' => $sid
      ]);
    }

    echo json_encode(['status' => 'success', 'message' => 'Ground Lesson(s) recorded.']);
    exit;
  }

  if ($type === 'Discovery') {
    $check = $db->prepare("SELECT COUNT(*) FROM wp_aircraft WHERE LOWER(tail_number) = LOWER(:tail)");
    $check->execute([':tail' => $tail_number]);

    if ($check->fetchColumn() == 0) {
      echo json_encode(['status' => 'error', 'message' => 'This tail number does not exist in your aircraft list.']);
      exit;
    }

    $stmt = $db->prepare("
      INSERT INTO wp_flight_logs (
        cfi_id,
        tail_number,
        student_notes,
        flight_category,
        total_flight_time,
        instructor_notes,
        flight_date
      ) VALUES (
        :cfi_id,
        :tail_number,
        :student_notes,
        'Discovery',
        :flight_time,
        :notes,
        :flight_date
      )
    ");
    $stmt->execute([
      ':cfi_id' => $cfi_id,
      ':tail_number' => $tail_number,
      ':student_notes' => $passenger_name,
      ':flight_time' => $flight_time,
      ':notes' => $notes,
      ':flight_date' => $now
    ]);

    echo json_encode(['status' => 'success', 'message' => 'Discovery Flight logged.']);
    exit;
  }

  echo json_encode(['status' => 'error', 'message' => 'Invalid entry type.']);
} catch (PDOException $e) {
  echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
}



