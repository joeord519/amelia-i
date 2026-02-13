<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/clear_cache.php');

header('Content-Type: application/json');

try {
  $input = json_decode(file_get_contents('php://input'), true);
  $required = ['start_time', 'end_time', 'reason'];

  foreach ($required as $field) {
    if (empty($input[$field])) throw new Exception("Missing $field");
  }

  $db = getDB();

  // All active CFIs
  $cfiStmt = $db->query("SELECT cfi_id FROM wp_cfis WHERE status = 'Active'");
  $cfis = $cfiStmt->fetchAll(PDO::FETCH_COLUMN);

  // All available aircraft
  $airStmt = $db->query("SELECT tail_number FROM wp_aircraft WHERE status = 'Available'");
  $planes = $airStmt->fetchAll(PDO::FETCH_COLUMN);

  foreach ($cfis as $cfi_id) {
    $stmt = $db->prepare("INSERT INTO wp_flight_schedule (flight_type, cfi_id, start_time, end_time, status, future_student_name) VALUES (?, ?, ?, ?, 'Scheduled', ?)");
    $stmt->execute(["Company Meeting", $cfi_id, $input['start_time'], $input['end_time'], $input['reason']]);
  }

  foreach ($planes as $tail) {
    $stmt = $db->prepare("INSERT INTO wp_flight_schedule (flight_type, tail_number, start_time, end_time, status, future_student_name) VALUES (?, ?, ?, ?, 'Scheduled', ?)");
    $stmt->execute(["Company Meeting", $tail, $input['start_time'], $input['end_time'], $input['reason']]);
  }

  clearSiteCache();
  echo json_encode(['success' => true]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
