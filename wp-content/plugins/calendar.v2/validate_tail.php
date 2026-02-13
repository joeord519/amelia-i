<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();
$tail = $_GET['tail'] ?? '';

if (!$tail) {
  echo json_encode(["valid" => false]);
  exit;
}

try {
  $stmt = $conn->prepare("SELECT COUNT(*) FROM wp_aircraft WHERE tail_number = ?");
  $stmt->execute([$tail]);
  $exists = $stmt->fetchColumn() > 0;

  echo json_encode(["valid" => $exists]);
} catch (Exception $e) {
  error_log("❌ Tail check error: " . $e->getMessage());
  echo json_encode(["valid" => false]);
}
