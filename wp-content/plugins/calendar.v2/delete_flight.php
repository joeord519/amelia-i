<?php
require_once('../db_connect.php');
$conn = getDB();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$flight_id = $data['id'] ?? null;
$undo = $data['undo'] ?? false;

if (!$flight_id) {
  echo json_encode(["success" => false, "message" => "Missing flight ID"]);
  exit;
}

try {
  $newStatus = $undo ? 'Scheduled' : 'Canceled';

  $stmt = $conn->prepare("UPDATE wp_flight_schedule SET status = ? WHERE id = ?");
  $stmt->execute([$newStatus, $flight_id]);

  echo json_encode(["success" => true, "undo" => !$undo]);
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "Update failed: " . $e->getMessage()]);
}
