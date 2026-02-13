<?php
require_once('../db_connect.php');
$conn = getDB();

try {
  $stmt = $conn->query("SELECT id, name, requires_cfi, requires_aircraft FROM wp_flight_types WHERE active = 1 ORDER BY name ASC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
} catch (Exception $e) {
  echo json_encode([]);
}

