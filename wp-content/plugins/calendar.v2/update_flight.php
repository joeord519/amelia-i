<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents("php://input"), true);
  $flightId = $data['id'];
  $start = $data['start'];
  $end = $data['end'];
  $pairedId = $data['paired_event_id'];

  if (!$flightId || !$start || !$end) {
    throw new Exception("Missing data.");
  }

  $conn = getDB();

  $stmt = $conn->prepare("UPDATE wp_flight_schedule SET start = :start, end = :end WHERE flight_id = :id OR cfi_event_id = :paired OR aircraft_event_id = :paired");
  $stmt->execute([
    ":start" => $start,
    ":end" => $end,
    ":id" => $flightId,
    ":paired" => $pairedId
  ]);

  echo json_encode(["success" => true]);
} catch (Exception $e) {
  echo json_encode(["success" => false, "message" => $e->getMessage()]);
}


