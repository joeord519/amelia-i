<?php
require_once('../db_connect.php');
$conn = getDB();

$phone = $_GET['phone'] ?? '';
$date = $_GET['date'] ?? '';

if (!$phone || !$date) {
  echo json_encode(["hasBooking" => false]);
  exit;
}

$stmt = $conn->prepare("
  SELECT COUNT(*) 
  FROM wp_flight_schedule 
  WHERE student_id = (
    SELECT student_id FROM wp_students WHERE phone = ?
  ) AND DATE(start_time) = ? AND status = 'Scheduled'
");
$stmt->execute([$phone, $date]);
$count = $stmt->fetchColumn();

echo json_encode(["hasBooking" => $count > 0]);


