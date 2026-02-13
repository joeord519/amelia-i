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

  // Lookup student_id from phone
  $stmt = $conn->prepare("SELECT student_id FROM wp_students WHERE phone = :phone");
  $stmt->execute([':phone' => $phone]);
  $student = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$student) {
    echo json_encode([]);
    exit;
  }

  $student_id = $student['student_id'];

  // Get completed flights + CFI info
  $stmt = $conn->prepare("
    SELECT 
      f.id,
      f.start_time,
      f.tail_number,
      f.total_flight_time,
      f.ground_time,
      f.cfi_signature,
      f.cfi_id,
      f.status,
      c.first_name AS cfi_first,
      c.last_name AS cfi_last,
      c.cfi_cert_id
    FROM wp_flight_logs f
    LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
    WHERE f.student_id = :student_id
      AND f.status = 'Completed'
    ORDER BY f.start_time DESC
  ");

  $stmt->execute([':student_id' => $student_id]);
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
}
