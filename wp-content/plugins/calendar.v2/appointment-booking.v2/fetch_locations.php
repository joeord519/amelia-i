<?php
require_once('../db_connect.php');
$conn = getDB();

try {
  $stmt = $conn->query("SELECT id, name, airport_code FROM wp_locations ORDER BY name ASC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
} catch (Exception $e) {
  echo json_encode([]);
}

