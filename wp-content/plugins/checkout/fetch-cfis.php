<?php
require_once('db_connect.php');
$pdo = getDB(); // Critical!

header('Content-Type: application/json');

$location = $_GET['location'] ?? '';

if (!$location) {
  echo json_encode(["error" => "No location specified"]);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT cfi_id, CONCAT(first_name, ' ', last_name) AS name FROM wp_cfis WHERE home_airport = ? AND status = 'Active'");
  $stmt->execute([$location]);
  $cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $cfis_mapped = array_map(function($row) {
    return [
      'cfi_id' => $row['cfi_id'],
      'cfi_name' => $row['name']
    ];
  }, $cfis);

  echo json_encode($cfis_mapped);
} catch (Throwable $e) {
  echo json_encode(["error" => "Fatal error", "details" => $e->getMessage()]);
}

