<?php
require 'db_connect.php';

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$selectedTime = $data['selectedTime'];

$stmt = $pdo->prepare("UPDATE wp_flight_schedule SET slot_status = 'Booked' WHERE start_time = ?");
$success = $stmt->execute([$selectedTime]);

if ($success) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => "Failed to book flight."]);
}
?>
