<?php
require_once('../db_connect.php');
$conn = getDB();
header('Content-Type: application/json');

try {
  $phone = $_GET['phone'] ?? '';
  if (!$phone) {
    echo json_encode([]);
    exit;
  }

  // Look up student_id
  $lookup = $conn->prepare("SELECT student_id FROM wp_students WHERE phone = :phone");
  $lookup->execute([':phone' => $phone]);
  $student = $lookup->fetch(PDO::FETCH_ASSOC);

  if (!$student) {
    echo json_encode([]);
    exit;
  }

  $student_id = $student['student_id'];

  // Query wp_flight_schedule
  $stmt = $conn->prepare("
    SELECT 
      f.id, 
      f.start_time, 
      f.end_time, 
      f.tail_number, 
      f.status, 
      c.first_name AS cfi_first, 
      c.last_name AS cfi_last
    FROM wp_flight_schedule f
    LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
    WHERE f.student_id = :student_id
      AND f.status = 'Scheduled'
    ORDER BY f.start_time DESC
  ");

  $stmt->execute([':student_id' => $student_id]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($results);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}






