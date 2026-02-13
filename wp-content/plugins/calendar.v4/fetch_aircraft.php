<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $query = $_GET['q'] ?? '';
  if (!$query) throw new Exception("Missing query");

  $db = getDB();

  $stmt = $db->prepare("
    SELECT tail_number 
    FROM wp_aircraft 
    WHERE tail_number LIKE ?
    ORDER BY tail_number ASC
    LIMIT 10
  ");
  $wild = '%' . strtoupper($query) . '%';
  $stmt->execute([$wild]);

  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  echo json_encode([]);
}
