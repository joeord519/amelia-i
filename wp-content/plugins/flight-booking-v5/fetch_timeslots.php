<?php
require 'db_connect.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$selectedDate = $data['selectedDate'];
$flightType = $data['flightType'];

$stmt = $pdo->prepare("SELECT start_time FROM wp_flight_schedule WHERE DATE(start_time) = ? AND flight_type = ? AND slot_status = 'Available'");
$stmt->execute([$selectedDate, $flightType]);
$timeslots = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(["timeslots" => $timeslots]);
?>
