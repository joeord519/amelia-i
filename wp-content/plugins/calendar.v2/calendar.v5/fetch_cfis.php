<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

$name = $_GET['name'] ?? '';
if (!$name || strlen($name) < 2) {
  echo json_encode([]);
  exit;
}

try {
  $db = getDB();
  $stmt = $db->prepare("
    SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name, phone
    FROM wp_cfis
    WHERE status = 'Active'
    AND (first_name LIKE ? OR last_name LIKE ?)
    ORDER BY last_name ASC
    LIMIT 10
  ");
  $stmt->execute(["{$name}%", "{$name}%"]);
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  echo json_encode([]);
}
