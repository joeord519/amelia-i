<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $conn = getDB();
  $stmt = $conn->query("SELECT tail_number, home_airport FROM wp_aircraft WHERE status = 'Available'");
  $aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($aircraft);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
?>
