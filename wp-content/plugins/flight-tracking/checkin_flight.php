<?php
require_once 'db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$student_id = $input['student_id'] ?? null;
$hobbs_end = $input['hobbs_end'] ?? null;
$tach_end = $input['tach_end'] ?? null;
$squawk = $input['squawk'] ?? '';

if (!$student_id || !$hobbs_end || !$tach_end) {
    echo json_encode(['status' => 'error', 'error' => 'Missing check-in data']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=dbqn6ggmq2vlto", "uizsmtjki2wdx", "7w26g#@$>iD5");

    // Get the most recent open flight
    $flightStmt = $pdo->prepare("SELECT * FROM wp_flight_logs 
        WHERE student_id = ? AND status = 'Checked Out' ORDER BY checkout_time DESC LIMIT 1");
    $flightStmt->execute([$student_id]);
    $flight = $flightStmt->fetch(PDO::FETCH_ASSOC);

    if (!$flight) {
        echo json_encode(['status' => 'error', 'error' => 'No active flight to check in']);
        exit;
    }

    $total_time = round($hobbs_end - $flight['start_hobbs'], 2);

    // Update flight log
    $update = $pdo->prepare("UPDATE wp_flight_logs SET 
        end_hobbs = ?, end_tach = ?, checkin_time = NOW(), 
        total_flight_time = ?, squawk_report = ?, status = 'Completed' 
        WHERE id = ?");
    $update->execute([$hobbs_end, $tach_end, $total_time, $squawk, $flight['id']]);

    // Deduct hours
    $deduct = $pdo->prepare("UPDATE wp_students 
        SET aircraft_hours_remaining = aircraft_hours_remaining - ? 
        WHERE student_id = ?");
    $deduct->execute([$total_time, $student_id]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'error' => 'Database error: ' . $e->getMessage()]);
}
