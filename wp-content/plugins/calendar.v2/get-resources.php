<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once(__DIR__ . '/db_connect.php');

try {
  $conn = getDB();
  $resources = [];

  // Aircraft
  $stmt = $conn->query("SELECT tail_number, manufacturer, model FROM wp_aircraft WHERE status = 'Available'");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $name = $row['tail_number'];
    if (!empty($row['manufacturer']) || !empty($row['model'])) {
      $name .= " (" . trim($row['manufacturer'] . ' ' . $row['model']) . ")";
    }

    $resources[] = [
      'id' => $row['tail_number'],
      'title' => $name,
      'eventBackgroundColor' => '#e0f2fe',
      'resourceType' => 'Aircraft',
      'group' => 'Aircraft'
    ];
  }

  // CFIs
  $stmt = $conn->query("SELECT cfi_id, first_name, last_name FROM wp_cfis");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $resources[] = [
      'id' => $row['cfi_id'],
      'title' => "{$row['first_name']} {$row['last_name']} (CFI)",
      'eventBackgroundColor' => '#fde68a',
      'resourceType' => 'CFI',
      'group' => 'CFI'
    ];
  }

  echo json_encode($resources);
} catch (Exception $e) {
  echo json_encode(['error' => 'Resource fetch failed: ' . $e->getMessage()]);
}
?>