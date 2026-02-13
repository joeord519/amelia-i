<?php
// Ensure valid JSON response
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once('db_connect.php');
$pdo = getDB();

$phone = $_GET['phone'] ?? '';
$cleanPhone = preg_replace('/\D/', '', $phone);

if (!$cleanPhone) {
  echo json_encode(['error' => 'Invalid phone number']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT aircraft_hours_remaining, instructor_hours_remaining
    FROM wp_students
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
  ");
  $stmt->execute([$cleanPhone]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$result) {
    echo json_encode(['error' => 'Student not found']);
    exit;
  }

  echo json_encode($result);
} catch (Exception $e) {
  error_log("❌ fetch-student-balances.php error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
  exit;
}


