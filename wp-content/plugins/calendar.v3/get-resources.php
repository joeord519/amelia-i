<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

try {
  $db = getDB();
  $resources = [];

  // ✅ Load Active CFIs
  $stmt = $db->query("SELECT cfi_id, first_name, last_name FROM wp_cfis WHERE status = 'Active'");
  while ($row = $stmt->fetch()) {
    $resources[] = [
      'id' => $row['cfi_id'],
      'title' => $row['first_name'] . ' ' . $row['last_name'] . ' (CFI)',
      'extendedProps' => [
        'group' => 'CFI'
      ]
    ];
  }

  // ✅ Load Available Aircraft
  $stmt = $db->query("SELECT tail_number, manufacturer, model, label_color FROM wp_aircraft WHERE status = 'Available'");
  while ($row = $stmt->fetch()) {
    $label = $row['tail_number'];
    if (!empty($row['manufacturer']) || !empty($row['model'])) {
      $label .= ' (' . trim($row['manufacturer'] . ' ' . $row['model']) . ')';
    }

    $resources[] = [
      'id' => $row['tail_number'],
      'title' => $label,
      'eventBackgroundColor' => $row['label_color'] ?: '#2563eb',
      'extendedProps' => [
        'group' => 'Aircraft'
      ]
    ];
  }

  echo json_encode($resources);

} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}