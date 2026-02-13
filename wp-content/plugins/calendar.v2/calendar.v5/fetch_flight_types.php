<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $db = getDB();
  $stmt = $db->query("SELECT id, name FROM wp_flight_types WHERE active = 1 ORDER BY name ASC");
  $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['success' => true, 'types' => $types]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

