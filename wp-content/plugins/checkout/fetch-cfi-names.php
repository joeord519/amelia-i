<?php
require_once('db_connect.php');
$pdo = getDB();

header('Content-Type: application/json');

$cfi1 = $_GET['cfi1'] ?? null;
$cfi2 = $_GET['cfi2'] ?? null;

$ids = array_filter([$cfi1, $cfi2]);

if (empty($ids)) {
  echo json_encode([]);
  exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$query = "SELECT cfi_id, CONCAT(first_name, ' ', last_name) AS name FROM wp_cfis WHERE cfi_id IN ($placeholders)";

try {
  $stmt = $pdo->prepare($query);
  $stmt->execute($ids);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $output = [];
  foreach ($results as $row) {
    $output[$row['cfi_id']] = $row['name'];
  }

  echo json_encode($output);
} catch (Throwable $e) {
  echo json_encode(["error" => "DB error", "details" => $e->getMessage()]);
}
