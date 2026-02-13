<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $db = getDB();
  $events = [];

  $colorMap = [];
  $stmtColors = $db->query("SELECT tail_number, label_color FROM wp_aircraft WHERE status = 'Available'");
  while ($ac = $stmtColors->fetch()) {
    $colorMap[$ac['tail_number']] = $ac['label_color'] ?: '#2563eb';
  }

  $stmt = $db->query("
    SELECT 
      fs.id,
      fs.flight_type,
      fs.event_title,
      ft.name AS flight_type_name,
      fs.start_time,
      fs.end_time,
      fs.tail_number,
      fs.cfi_id,
      fs.future_student_name,
      fs.future_student_phone,
      s.first_name AS student_first,
      s.last_name AS student_last,
      s.phone AS student_phone,
      c.first_name AS cfi_first,
      c.last_name AS cfi_last
    FROM wp_flight_schedule fs
    LEFT JOIN wp_flight_types ft ON fs.flight_type = ft.id
    LEFT JOIN wp_students s ON fs.student_id = s.student_id
    LEFT JOIN wp_cfis c ON fs.cfi_id = c.cfi_id
    WHERE fs.status = 'Scheduled'
  ");

  while ($row = $stmt->fetch()) {
    $flightId = $row['id'];
    $tz = new DateTimeZone('America/Chicago');
    $start = (new DateTime($row['start_time'], $tz))->format('c');
    $end   = (new DateTime($row['end_time'], $tz))->format('c');

    $tail = $row['tail_number'];
    $cfi = $row['cfi_id'];
    $color = $colorMap[$tail] ?? '#2563eb';

    $studentName = trim(($row['student_first'] ?? '') . ' ' . ($row['student_last'] ?? ''));
    $studentPhone = $row['student_phone'] ?? '';
    $cfiName = trim(($row['cfi_first'] ?? '') . ' ' . ($row['cfi_last'] ?? ''));
    $flightType = $row['flight_type_name'] ?? $row['flight_type'] ?? 'Flight';
    $eventTitle = $row['event_title'] ?? '';

    if (stripos($flightType, 'Company Event') !== false) {
      $title = $eventTitle ?: 'Company Event';
    } else {
      $title = 'Flight - ' . $studentName;
    }

    $props = [
      'flight_id' => $flightId,
      'flight_type' => $flightType,
      'event_title' => $eventTitle,
      'tail_number' => $tail,
      'cfi_id' => $cfi,
      'cfi_name' => $cfiName,
      'student_name' => $studentName,
      'student_phone' => $studentPhone,
      'color' => $color
    ];

    if (!empty($cfi)) {
      $events[] = [
        'id' => "evt-{$flightId}-CFI",
        'resourceId' => $cfi,
        'title' => $title,
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => array_merge($props, [
          'paired_event_id' => "evt-{$flightId}-AC",
          'group' => 'CFI'
        ])
      ];
    }

    if (!empty($tail)) {
      $events[] = [
        'id' => "evt-{$flightId}-AC",
        'resourceId' => $tail,
        'title' => $title,
        'start' => $start,
        'end' => $end,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => array_merge($props, [
          'paired_event_id' => "evt-{$flightId}-CFI",
          'group' => 'Aircraft'
        ])
      ];
    }
  }

  echo json_encode(['events' => $events]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
