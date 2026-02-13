<?php
require_once(__DIR__ . '/../db_connect.php'); // ✅ adjust for readonly folder
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  $db = getDB();

  $stmt = $db->query("
    SELECT 
      f.id,
      f.start_time,
      f.end_time,
      f.tail_number,
      f.cfi_id,
      f.student_id AS direct_student_id,
      f.future_student_name,
      ft.name AS flight_type,
      a.label_color,
      s1.first_name AS direct_first,
      s1.last_name AS direct_last,
      s1.phone AS direct_phone,
      s2.first_name AS joined_first,
      s2.last_name AS joined_last,
      s2.phone AS joined_phone,
      c.first_name AS cfi_first,
      c.last_name AS cfi_last
    FROM wp_flight_schedule f
    LEFT JOIN wp_flight_types ft ON f.flight_type_id = ft.id
    LEFT JOIN wp_aircraft a ON f.tail_number = a.tail_number
    LEFT JOIN wp_students s1 ON f.student_id = s1.student_id
    LEFT JOIN wp_flight_students fs ON f.id = fs.flight_id
    LEFT JOIN wp_students s2 ON fs.student_id = s2.student_id
    LEFT JOIN wp_cfis c ON f.cfi_id = c.cfi_id
    ORDER BY f.start_time ASC
  ");

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $events = [];

  foreach ($rows as $row) {
    $flightType = $row['flight_type'] ?? 'Flight';
    $tail       = $row['tail_number'] ?? '';
    $cfiId      = $row['cfi_id'] ?? '';
    $cfiName    = trim(($row['cfi_first'] ?? '') . ' ' . ($row['cfi_last'] ?? ''));

    // ✅ Resolve student name
    $studentName = '';
    $studentPhone = '';

    if (!empty($row['direct_first']) || !empty($row['direct_last'])) {
      $studentName = trim("{$row['direct_first']} {$row['direct_last']}");
      $studentPhone = $row['direct_phone'] ?? '';
    } elseif (!empty($row['joined_first']) || !empty($row['joined_last'])) {
      $studentName = trim("{$row['joined_first']} {$row['joined_last']}");
      $studentPhone = $row['joined_phone'] ?? '';
    } elseif (!empty($row['future_student_name'])) {
      $studentName = $row['future_student_name'];
    } else {
      $studentName = 'N/A';
    }

    $title = "$flightType - $studentName - $tail";

    // Color logic
    $color = $row['label_color'] ?: '#007BFF';
    if (strtolower($flightType) === 'ground lesson') $color = '#22c55e';
    if (strtolower($flightType) === 'company event') {
      $color = '#1e40af';
      $title = 'Company Event';
    }

    // CFI row
    if (!empty($cfiId)) {
      $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'resourceId' => $cfiId,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => [
          'flight_id' => $row['id'],
          'flight_type' => $flightType,
          'student_name' => $studentName,
          'student_phone' => $studentPhone,
          'tail_number' => $tail,
          'cfi_id' => $cfiId,
          'cfi_name' => $cfiName
        ]
      ];
    }

    // Aircraft row
    if (!empty($tail)) {
      $events[] = [
        'id' => $row['id'] . '-ac',
        'title' => $title,
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'resourceId' => $tail,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => [
          'flight_id' => $row['id'],
          'flight_type' => $flightType,
          'student_name' => $studentName,
          'student_phone' => $studentPhone,
          'tail_number' => $tail,
          'cfi_id' => $cfiId,
          'cfi_name' => $cfiName
        ]
      ];
    }
  }

  echo json_encode($events); // ✅ Flat array for readonly mode

} catch (Exception $e) {
  echo json_encode([]); // fallback on failure
}

