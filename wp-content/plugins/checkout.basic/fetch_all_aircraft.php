<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $db = getDB();
  $stmt = $db->query("SELECT tail_number FROM wp_aircraft ORDER BY tail_number ASC");
  echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
  echo json_encode([]);
}
