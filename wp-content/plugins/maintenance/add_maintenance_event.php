<?php
require_once(__DIR__ . '/includes/db_connect.php');
$db = getDB();

header('Content-Type: application/json'); // Force JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tail_number = $_POST['tail_number'] ?? '';
  $event_type = $_POST['event_type'] ?? '';
  $tracking_type = $_POST['tracking_type'] ?? 'TACH';
  $notes = $_POST['notes'] ?? '';

  // Log raw incoming POST
  error_log("RAW POST: " . json_encode($_POST));

  $interval_hours = null;
  $last_performed_tach = null;
  $interval_days = null;
  $last_performed_date = null;

  if ($tracking_type === 'TACH') {
    $interval_hours = floatval($_POST['interval_hours'] ?? 0);
    $last_performed_tach = floatval($_POST['last_performed_tach'] ?? 0);
  } elseif ($tracking_type === 'DATE') {
    $interval_days = intval($_POST['interval_days'] ?? 0);
    $inputDate = $_POST['last_performed_date'] ?? null;

    if ($inputDate && strpos($inputDate, '-') !== false) {
      $last_performed_date = $inputDate;
    } else {
      $timestamp = strtotime($inputDate);
      $last_performed_date = $timestamp ? date('Y-m-d', $timestamp) : null;
    }
  }

  // ✅ Ensure all NOT NULL fields are filled
  if ($tracking_type === 'DATE') {
    $interval_hours = 0.0;
    $last_performed_tach = 0.0;
  }
  if ($tracking_type === 'TACH') {
    $interval_days = 0;
    $last_performed_date = null;
  }

  try {
    $stmt = $db->prepare("
      INSERT INTO wp_maintenance_schedule 
      (tail_number, event_type, tracking_type, interval_hours, last_performed_tach, interval_days, last_performed_date, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    error_log("INSERT DATA: " . json_encode([
      $tail_number,
      $event_type,
      $tracking_type,
      $interval_hours,
      $last_performed_tach,
      $interval_days,
      $last_performed_date,
      $notes
    ]));

    $stmt->execute([
      $tail_number,
      $event_type,
      $tracking_type,
      $interval_hours,
      $last_performed_tach,
      $interval_days,
      $last_performed_date,
      $notes
    ]);

    echo json_encode(['success' => true]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
}
?>



