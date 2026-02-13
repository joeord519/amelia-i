<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $conn = getDB();
  $stmt = $conn->query("SELECT cfi_id, first_name, last_name, home_airport FROM wp_cfis WHERE status = 'Active'");
  $cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($cfis);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
?>
