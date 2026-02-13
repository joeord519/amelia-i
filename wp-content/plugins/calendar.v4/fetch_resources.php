<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

try {
  $db = getDB();
  $resources = [];

  // Aircraft Resources
  $stmt = $db->query("SELECT tail_number, label_color, manufacturer, model FROM wp_aircraft WHERE status = 'Available'");
  while ($row = $stmt->fetch()) {
    $resources[] = [
      'id' => $row['tail_number'],
      'title' => "{$row['tail_number']} ({$row['manufacturer']} {$row['model']})",
      'eventBackgroundColor' => $row['label_color'],
      'extendedProps' => [ 'group' => 'Aircraft' ]
    ];
  }

  // CFI Resources
  $stmt = $db->query("SELECT cfi_id, first_name, last_name FROM wp_cfis WHERE status = 'Active'");
  while ($row = $stmt->fetch()) {
    $resources[] = [
      'id' => $row['cfi_id'],
      'title' => "{$row['first_name']} {$row['last_name']}",
      'eventBackgroundColor' => "#facc15",
      'extendedProps' => [ 'group' => 'CFI' ]
    ];
  }

  echo json_encode($resources);
} catch (Exception $e) {
  echo json_encode([]);
}
