<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['flight_id']) || empty($input['start']) || empty($input['end'])) {
  echo json_encode(["success" => false, "message" => "Missing required fields."]);
  exit;
}

try {
  $stmt = $conn->prepare("UPDATE wp_flight_schedule SET start_time = ?, end_time = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$input['start'], $input['end'], $input['flight_id']]);
  echo json_encode(["success" => true]);
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>