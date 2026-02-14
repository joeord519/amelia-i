<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

try {
  $db = getDB();
  $resources = [];
  $availableOnly = isset($_GET['available_only']) && $_GET['available_only'] === '1';

  $aircraftSql = "SELECT tail_number, label_color, manufacturer, model, status
                  FROM wp_aircraft";
  if ($availableOnly) {
    $aircraftSql .= " WHERE status = 'Available'";
  }
  $aircraftSql .= " ORDER BY tail_number ASC";

  $stmt = $db->query($aircraftSql);
  while ($row = $stmt->fetch()) {
    $nickname = trim(($row['manufacturer'] ?? '') . ' ' . ($row['model'] ?? ''));
    $resources[] = [
      'id' => $row['tail_number'],
      'title' => $row['tail_number'],
      'eventBackgroundColor' => $row['label_color'] ?: '#2563eb',
      'extendedProps' => [
        'group' => 'Aircraft',
        'nickname' => $nickname,
        'status' => $row['status'] ?? 'Unknown',
      ]
    ];
  }

  $stmt = $db->query("SELECT cfi_id, first_name, last_name FROM wp_cfis WHERE status = 'Active'");
  while ($row = $stmt->fetch()) {
    $resources[] = [
      'id' => $row['cfi_id'],
      'title' => "{$row['first_name']} {$row['last_name']}",
      'eventBackgroundColor' => '#facc15',
      'extendedProps' => [
        'group' => 'CFI'
      ]
    ];
  }

  echo json_encode($resources);
} catch (Exception $e) {
  echo json_encode([]);
}
