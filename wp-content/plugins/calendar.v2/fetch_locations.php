<?php
require_once('db_connect.php');
header('Content-Type: application/json');

$conn = getDB();

try {
  $stmt = $conn->query("
    SELECT airport_code, name 
    FROM wp_locations 
    WHERE active = 1 
    ORDER BY name ASC
  ");

  $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($locations);
} catch (Exception $e) {
  error_log("❌ fetch_locations.php error: " . $e->getMessage());
  echo json_encode([]);
}
