<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

$tail = strtoupper(trim($_GET['tail'] ?? ''));

if (!$tail) {
  echo json_encode(['valid' => false]);
  exit;
}

try {
  $db = getDB();
  $stmt = $db->prepare("SELECT COUNT(*) FROM wp_aircraft WHERE tail_number = ?");
  $stmt->execute([$tail]);
  $valid = $stmt->fetchColumn() > 0;
  echo json_encode(['valid' => $valid]);
} catch (Exception $e) {
  echo json_encode(['valid' => false, 'error' => $e->getMessage()]);
}
