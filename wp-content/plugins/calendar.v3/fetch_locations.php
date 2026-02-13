<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $db = getDB();
  $stmt = $db->query("SELECT airport_code, name FROM wp_locations WHERE active = 1");
  $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($locations);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
