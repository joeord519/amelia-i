<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  $db = getDB();
  $db->exec("SET time_zone = 'America/Chicago'");

  $stmt = $db->query("
    SELECT 
      f.id,
      f.start_time,
      f.end_time,
      f.tail_number,
      f.cfi_id,
      f.flight_type,
      f.flight_type_id,
      f.ground_student_1,
      f.ground_student_2,
      f.ground_student_3,
      f.ground_student_4,
      f.lead_id AS flight_lead_id,

      ft.name AS flight_type_name,
      a.label_color,

      c.first_name AS cfi_first,
      c.last_name  AS cfi_last,

      -- Direct student (wp_flight_schedule.student_id)
      s1.first_name AS direct_first,
      s1.last_name  AS direct_last,
      s1.phone      AS direct_phone,

      -- Joined student (wp_flight_students join table)
      s2.first_name AS joined_first,
      s2.last_name  AS joined_last,
      s2.phone      AS joined_phone,

      -- Discovery lead (via join, if it works)
      l.first_name  AS discovery_first,
      l.last_name   AS discovery_last,
      l.phone       AS discovery_phone

    FROM wp_flight_schedule f
    LEFT JOIN wp_flight_types    ft ON f.flight_type_id = ft.id
    LEFT JOIN wp_aircraft        a  ON f.tail_number   = a.tail_number
    LEFT JOIN wp_cfis            c  ON f.cfi_id        = c.cfi_id
    LEFT JOIN wp_students        s1 ON f.student_id    = s1.student_id
    LEFT JOIN wp_flight_students fs ON f.id            = fs.flight_id
    LEFT JOIN wp_students        s2 ON fs.student_id   = s2.student_id
    LEFT JOIN wp_leads           l  ON f.lead_id       = l.id

    ORDER BY f.start_time ASC
  ");

  $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $events = [];
  $seen   = [];

  foreach ($rows as $row) {
    $flightId   = $row['id'];
    $flightType = $row['flight_type'] ?? $row['flight_type_name'] ?? 'Flight';

    if (isset($seen[$flightId])) {
      continue;
    }
    $seen[$flightId] = true;

    $cfiId   = $row['cfi_id'] ?? '';
    $tail    = $row['tail_number'] ?? '';
    $cfiName = trim(($row['cfi_first'] ?? '') . ' ' . ($row['cfi_last'] ?? ''));

    $isDiscovery = (stripos($flightType, 'discovery') !== false);

    /* =============================
     * 1) GROUND LESSON EVENTS
     * ============================= */
    if (stripos($flightType, 'ground') !== false) {
      $ids = array_filter([
        $row['ground_student_1'],
        $row['ground_student_2'],
        $row['ground_student_3'],
        $row['ground_student_4']
      ]);

      $multiStudents = [];

      if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtS = $db->prepare("
          SELECT student_id, CONCAT(first_name, ' ', last_name) AS name, phone
          FROM wp_students
          WHERE student_id IN ($placeholders)
        ");
        $stmtS->execute($ids);
        $multiStudents = $stmtS->fetchAll(PDO::FETCH_ASSOC);
      }

      $events[] = [
        'id'              => $flightId,
        'title'           => 'Ground Lesson x ' . count($multiStudents),
        'start'           => $row['start_time'],
        'end'             => $row['end_time'],
        'resourceId'      => $cfiId,
        'backgroundColor' => '#22c55e',
        'borderColor'     => '#22c55e',
        'textColor'       => '#ffffff',
        'extendedProps'   => [
          'flight_id'   => $flightId,
          'flight_type' => $flightType,
          'tail_number' => '',
          'cfi_id'      => $cfiId,
          'cfi_name'    => $cfiName,
          'students'    => $multiStudents
        ]
      ];
      continue;
    }

    /* =============================
     * 2) ALL OTHER FLIGHTS
     *    (Dual, Solo, Discovery, etc.)
     * ============================= */

    $studentName  = '';
    $studentPhone = '';

    // 2a) Direct student on the flight record
    if (!empty($row['direct_first']) || !empty($row['direct_last'])) {
      $studentName  = trim("{$row['direct_first']} {$row['direct_last']}");
      $studentPhone = $row['direct_phone'] ?? '';

    // 2b) Joined student from wp_flight_students
    } elseif (!empty($row['joined_first']) || !empty($row['joined_last'])) {
      $studentName  = trim("{$row['joined_first']} {$row['joined_last']}");
      $studentPhone = $row['joined_phone'] ?? '';

    // 2c) Discovery via JOIN to wp_leads
    } elseif ($isDiscovery && (!empty($row['discovery_first']) || !empty($row['discovery_last']))) {
      $studentName  = trim("{$row['discovery_first']} {$row['discovery_last']}");
      $studentPhone = $row['discovery_phone'] ?? '';

    // 2d) Discovery fallback: do a direct lookup by lead_id
    } elseif ($isDiscovery && !empty($row['flight_lead_id'])) {
      $stmtLead = $db->prepare("
        SELECT first_name, last_name, phone
        FROM wp_leads
        WHERE id = ?
        LIMIT 1
      ");
      $stmtLead->execute([$row['flight_lead_id']]);
      $leadRow = $stmtLead->fetch(PDO::FETCH_ASSOC);

      if ($leadRow) {
        $studentName  = trim("{$leadRow['first_name']} {$leadRow['last_name']}");
        $studentPhone = $leadRow['phone'] ?? '';
      }

      if ($studentName === '') {
        $studentName = 'N/A';
      }
      if ($studentPhone === '') {
        $studentPhone = 'N/A';
      }

    // 2e) Nothing found
    } else {
      $studentName  = 'N/A';
      $studentPhone = 'N/A';
    }

    $title = "$flightType - $studentName - $tail";
    $color = $row['label_color'] ?: '#007BFF';

    // Main event on CFI resource
    $events[] = [
      'id'              => $flightId,
      'title'           => $title,
      'start'           => $row['start_time'],
      'end'             => $row['end_time'],
      'resourceId'      => $cfiId,
      'backgroundColor' => $color,
      'borderColor'     => $color,
      'textColor'       => '#ffffff',
      'extendedProps'   => [
        'flight_id'            => $flightId,
        'flight_type'          => $flightType,
        'student_name'         => $studentName,
        'student_phone'        => $studentPhone,
        'future_student_name'  => $isDiscovery ? $studentName  : null,
        'future_student_phone' => $isDiscovery ? $studentPhone : null,
        'tail_number'          => $tail,
        'cfi_id'               => $cfiId,
        'cfi_name'             => $cfiName,
        'paired_event_id'      => "{$flightId}-ac"
      ]
    ];

    // Paired event on Aircraft resource
    $events[] = [
      'id'              => "{$flightId}-ac",
      'title'           => $title,
      'start'           => $row['start_time'],
      'end'             => $row['end_time'],
      'resourceId'      => $tail,
      'backgroundColor' => $color,
      'borderColor'     => $color,
      'textColor'       => '#ffffff',
      'extendedProps'   => [
        'flight_id'            => $flightId,
        'flight_type'          => $flightType,
        'student_name'         => $studentName,
        'student_phone'        => $studentPhone,
        'future_student_name'  => $isDiscovery ? $studentName  : null,
        'future_student_phone' => $isDiscovery ? $studentPhone : null,
        'tail_number'          => $tail,
        'cfi_id'               => $cfiId,
        'cfi_name'             => $cfiName,
        'paired_event_id'      => "{$flightId}"
      ]
    ];
  }

  echo json_encode(['events' => $events]);
} catch (Exception $e) {
  echo json_encode(['events' => [], 'error' => $e->getMessage()]);
}

