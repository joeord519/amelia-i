<?php
require_once('../db_connect.php');
$conn = getDB();

header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
if (!$phone) {
  echo json_encode([]);
  exit;
}

$stmt = $conn->prepare("
  SELECT 
    f.id, f.start_time, f.end_time, f.flight_type, f.tail_number, f.status,
    c.first_name AS cfi_first, c.last_name AS cfi_last
  FROM wp_flight_schedule f
  LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
  WHERE f.student_id = (
    SELECT student_id FROM wp_students WHERE phone = ?
  )
  ORDER BY f.start_time ASC
");

$stmt->execute([$phone]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
