<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $db = getDB();

  // Get all ACTIVE wings with logo_url
  $wings = [];
  $stmt = $db->prepare("
    SELECT id, wing_name, logo_url
    FROM wp_cfi_wings
    WHERE status = 'Active'
  ");
  $stmt->execute();
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $logo = $row['logo_url'];
    if (strpos($logo, 'http') !== 0) {
      $logo = 'https://amelia-i.com' . $logo;
    }

    $wings[$row['id']] = [
      'name' => $row['wing_name'],
      'logo_url' => $logo,
      'daily_hours' => 0,
      'weekly_hours' => 0,
      'monthly_hours' => 0,
      'flights' => []
    ];
  }

  // CFI map
  $stmt = $db->prepare("SELECT cfi_id, wing_id, CONCAT(first_name, ' ', last_name) AS name FROM wp_cfis WHERE wing_id IS NOT NULL");
  $stmt->execute();
  $cfiMap = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cfiMap[$row['cfi_id']] = [
      'wing_id' => $row['wing_id'],
      'cfi_name' => $row['name']
    ];
  }

  // Student name map
  $stmt = $db->prepare("SELECT student_id, CONCAT(first_name, ' ', last_name) AS name FROM wp_students");
  $stmt->execute();
  $studentMap = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $studentMap[$row['student_id']] = $row['name'];
  }

  $today = date('Y-m-d');
  $weekStart = date('Y-m-d', strtotime('monday this week'));
  $monthStart = date('Y-m-01');

  // Flight logs: flying + completed using created_at
  $stmt = $db->prepare("
    SELECT id, cfi_id, student_id, description, status, total_flight_time,
           DATE(created_at) AS date
    FROM wp_flight_logs
    WHERE status IN ('completed', 'flying') AND DATE(created_at) >= ?
  ");
  $stmt->execute([$monthStart]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cfi_id = $row['cfi_id'];
    if (!isset($cfiMap[$cfi_id])) continue;

    $wing_id = $cfiMap[$cfi_id]['wing_id'];
    if (!isset($wings[$wing_id])) continue;

    $duration = floatval($row['total_flight_time'] ?? 0);
    $date = $row['date'];

    if ($date === $today) $wings[$wing_id]['daily_hours'] += $duration;
    if ($date >= $weekStart) $wings[$wing_id]['weekly_hours'] += $duration;
    if ($date >= $monthStart) $wings[$wing_id]['monthly_hours'] += $duration;

    $wings[$wing_id]['flights'][] = [
      'status' => $row['status'],
      'flight_type' => $row['description'],
      'student_name' => $studentMap[$row['student_id']] ?? 'Unknown',
      'cfi_name' => $cfiMap[$cfi_id]['cfi_name'],
    ];
  }

  // Round totals to 2 decimal places
  foreach ($wings as &$wing) {
    $wing['daily_hours'] = round($wing['daily_hours'], 2);
    $wing['weekly_hours'] = round($wing['weekly_hours'], 2);
    $wing['monthly_hours'] = round($wing['monthly_hours'], 2);
  }
  unset($wing);

  // Scheduled flights (today only)
  $stmt = $db->prepare("
    SELECT id, cfi_id, student_id, flight_type, start_time
    FROM wp_flight_schedule
    WHERE DATE(start_time) = ?
  ");
  $stmt->execute([$today]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cfi_id = $row['cfi_id'];
    if (!isset($cfiMap[$cfi_id])) continue;

    $wing_id = $cfiMap[$cfi_id]['wing_id'];
    if (!isset($wings[$wing_id])) continue;

    $wings[$wing_id]['flights'][] = [
      'status' => 'scheduled',
      'flight_type' => $row['flight_type'],
      'student_name' => $studentMap[$row['student_id']] ?? 'Unknown',
      'cfi_name' => $cfiMap[$cfi_id]['cfi_name'],
    ];
  }

  echo json_encode(array_values($wings));

} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}

