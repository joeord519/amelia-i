<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $tailNumber = $_POST['tail_number'] ?? null;
  $studentId = $_POST['student_id'] ?? null;

  if (!$tailNumber || !$studentId) {
    throw new Exception("Missing or invalid input.");
  }

  $db = getDB();

  function getSoloSlots($tailNumber, $studentId, $db) {
    $recommended = [];

    for ($i = 0; $i <= 21; $i++) {
      $date = date('Y-m-d', strtotime("+$i days"));
      foreach ([7, 9, 11, 13, 15, 17] as $hour) {
        $start = "$date $hour:00:00";
        $end = date('Y-m-d H:i:s', strtotime("+2 hours", strtotime($start)));

        $conflictQuery = "
          SELECT COUNT(*) FROM wp_flight_schedule 
          WHERE (
            (start_time < :end AND end_time > :start)
            AND tail_number = :tailNumber
          )
        ";

        $stmt = $db->prepare($conflictQuery);
        $stmt->execute([
          ':start' => $start,
          ':end' => $end,
          ':tailNumber' => $tailNumber
        ]);

        if ($stmt->fetchColumn() > 0) continue;

        $recommended[] = [
          'start_time' => $start,
          'end_time' => $end,
          'aircraft' => $tailNumber,
          'cfi_id' => null,
          'cfi_name' => 'N/A'
        ];

        if (count($recommended) >= 3) break 2;
      }
    }

    return $recommended;
  }

  $slots = getSoloSlots($tailNumber, $studentId, $db);

  echo json_encode([
    'status' => 'success',
    'slots' => $slots
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => $e->getMessage()
  ]);
}

