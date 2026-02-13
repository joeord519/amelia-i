<?php
require_once('../db_connect.php');
$conn = getDB();

$locId = $_GET['loc'] ?? '';
header('Content-Type: application/json');

try {
  $stmt = $conn->prepare("
    SELECT tail_number AS id, tail_number 
    FROM wp_aircraft 
    WHERE home_airport = ?
      AND status = 'Available'
    ORDER BY tail_number ASC
  ");
  $stmt->execute([$locId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    "received_loc" => $locId,
    "sql" => "home_airport = '$locId'",
    "results" => $rows
  ]);
} catch (Exception $e) {
  echo json_encode([
    "error" => $e->getMessage(),
    "loc_received" => $locId
  ]);
}


