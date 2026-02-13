<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

try {
  $db = getDB();

  // Load CFIs with Wings
  $cfis = [];
  $stmt = $db->query("SELECT id, name, wing_id FROM wp_cfis");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cfis[$row['id']] = [
      'name' => $row['name'],
      'wing_id' => $row['wing_id'],
      'hours_today' => 0,
      'hours_week' => 0,
      'hours_month' => 0
    ];
  }

  // Load Wings
  $wings = [];
  $stmt = $db->query("SELECT id, wing_name FROM wp_cfi_wings");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $wings[$row['id']] = [
      'name' => $row['wing_name'],
      'logo' => 'https://amelia-i.com/wp-content/uploads/cfi_wings/' . 
          'wing_' . $row['id'] . '_' . strtolower(str_replace(' ', '_', $row['wing_name'])) . '_wing_logo.png',
      'hours_today' => 0,
      'hours_week' => 0,
      'hours_month' => 0
    ];
  }

  // Time ranges
  $today = date('Y-m-d');
  $startOfWeek = date('Y-m-d', strtotime('monday this week'));
  $startOfMonth = date('Y-m-01');

  // Flights from flight_logs (COMPLETED)
  $stmt = $db->query("
    SELECT fl.id, fl.start_time, fl.end_time, fl.cfi_id, fl.student_id, fl.appointment_type,
       TIMESTAMPDIFF(MINUTE, fl.start_time, fl.end_time) AS duration
        FROM wp_flight_logs fl
        WHERE fl.status = 'Completed'
        AND fl.start_time IS NOT NULL
        AND fl.end_time IS NOT NULL
        WHERE fl.end_time IS NOT NULL AND fl.start_time IS NOT NULL
  ");

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cfi_id = $row['cfi_id'];
    $wing_id = $cfis[$cfi_id]['wing_id'] ?? null;
    $minutes = intval($row['duration']);
    $hours = round($minutes / 60, 2);
    $date = substr($row['start_time'], 0, 10);

    if ($date >= $today) $cfis[$cfi_id]['hours_today'] += $hours;
    if ($date >= $startOfWeek) $cfis[$cfi_id]['hours_week'] += $hours;
    if ($date >= $startOfMonth) $cfis[$cfi_id]['hours_month'] += $hours;

    if ($wing_id !== null) {
      if ($date >= $today) $wings[$wing_id]['hours_today'] += $hours;
      if ($date >= $startOfWeek) $wings[$wing_id]['hours_week'] += $hours;
      if ($date >= $startOfMonth) $wings[$wing_id]['hours_month'] += $hours;
    }
  }

  // Today’s flights from wp_flight_schedule
  $stmt = $db->prepare("
    SELECT fs.*, s.first_name, s.last_name, c.name AS cfi_name
    FROM wp_flight_schedule fs
    LEFT JOIN wp_students s ON fs.student_id = s.id
    LEFT JOIN wp_cfis c ON fs.cfi_id = c.id
    WHERE DATE(fs.start_time) = ?
    ORDER BY fs.start_time ASC
  ");
  $stmt->execute([$today]);

  $flights_today = [];
  $solo_celebrations = [];

  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $flightType = $row['flight_type'];
    $status = $row['status'];

    // Special solo flight congrats
    if ($flightType == 'Solo' && $status == 'In Flight') {
      $solo_celebrations[] = [
        'student' => "{$row['first_name']} {$row['last_name']}",
        'time' => substr($row['start_time'], 11, 5)
      ];
    }

    $flights_today[] = [
      'cfi' => $row['cfi_name'] ?? 'SOLO',
      'student' => "{$row['first_name']} {$row['last_name']}",
      'flight_type' => $flightType,
      'scheduled' => substr($row['start_time'], 11, 5),
      'status' => strtoupper($status)
    ];
  }

  // Find top CFI of the day
  $topCfi = array_reduce(array_keys($cfis), function($carry, $cfiId) use ($cfis) {
    return ($carry === null || $cfis[$cfiId]['hours_today'] > $cfis[$carry]['hours_today']) ? $cfiId : $carry;
  });

  echo json_encode([
    'wings' => array_values($wings),
    'flights_today' => $flights_today,
    'top_cfi' => $cfis[$topCfi]['name'] ?? null,
    'solo_flights' => $solo_celebrations
  ]);
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
