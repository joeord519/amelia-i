<?php
require_once(__DIR__ . '/db_connect.php');

$airport = trim($_GET['home_airport'] ?? '');
if (!$airport) exit(json_encode([]));

try {
  $db = getDB();
  $stmt = $db->prepare("
    SELECT cfi_id, CONCAT(first_name, ' ', last_name) AS name
    FROM wp_cfis
    WHERE home_airport = :a
      AND status = 'active'
    ORDER BY first_name
  ");
  $stmt->execute([':a' => $airport]);

  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
  echo json_encode([]);
}

