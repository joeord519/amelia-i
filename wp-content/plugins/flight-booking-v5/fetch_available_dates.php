<?php
require 'db_connect.php';

header("Content-Type: application/json");

$stmt = $pdo->prepare("SELECT DISTINCT DATE(start_time) as available_date FROM wp_flight_schedule WHERE slot_status = 'Available'");
$stmt->execute();
$dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode(["dates" => $dates]);
?>

