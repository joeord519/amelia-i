<?php
require_once('db_connect.php');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  $conn = getDB();
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => "DB connection failed: " . $e->getMessage()]);
  exit;
}

// ✅ Get and decode input
$data_raw = file_get_contents("php://input");
error_log("📥 Incoming payload: " . $data_raw);
$data = json_decode($data_raw, true);

// ✅ Extract fields
$flight_id = $data['id'] ?? null;
$new_start = $data['start'] ?? null;
$new_end   = $data['end'] ?? null;

if (!$flight_id || !$new_start || !$new_end) {
  echo json_encode(["success" => false, "message" => "Missing required fields"]);
  exit;
}

try {
  $stmt = $conn->prepare("
    UPDATE wp_flight_schedule 
    SET start_time = ?, end_time = ?
    WHERE id = ?
  ");
  $stmt->execute([$new_start, $new_end, $flight_id]);

  // ✅ Always return something!
  echo json_encode(["success" => true]);
  exit;

} catch (Exception $e) {
  error_log("❌ update_flight.php failed: " . $e->getMessage());
  echo json_encode(["success" => false, "message" => "Update failed"]);
  exit;
}

