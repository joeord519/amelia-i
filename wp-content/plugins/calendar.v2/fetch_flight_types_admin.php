<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();

try {
  $stmt = $conn->query("
    SELECT id, name, requires_cfi, requires_aircraft, default_duration, active
    FROM wp_flight_types
    ORDER BY FIELD(name, 'Discovery Flight') DESC, name ASC
  ");

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
} catch (Exception $e) {
  error_log("❌ fetch_flight_types_admin error: " . $e->getMessage());
  echo json_encode([]);
}
