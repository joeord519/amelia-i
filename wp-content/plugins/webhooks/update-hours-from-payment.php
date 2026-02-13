<?php
file_put_contents(__DIR__ . '/webhook_debug.log', date('Y-m-d H:i:s') . " - Webhook hit\n", FILE_APPEND);

$input = json_decode(file_get_contents("php://input"), true);
file_put_contents(__DIR__ . '/webhook_debug.log', print_r($input, true), FILE_APPEND);

// Match keys exactly to Gravity form webhook config
$phone      = trim($input['Phone'] ?? '');
$aircraft   = (float)($input['Aircraft Hours Purchased'] ?? 0);
$instructor = (float)($input['Instructor Hours Purchased'] ?? 0);

if (!$phone) {
    file_put_contents(__DIR__ . '/webhook_debug.log', "❌ Missing phone\n", FILE_APPEND);
    http_response_code(400);
    exit;
}

require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

$stmt = $conn->prepare("UPDATE wp_students SET 
    aircraft_hours_remaining = aircraft_hours_remaining + ?, 
    instructor_hours_remaining = instructor_hours_remaining + ?
    WHERE phone = ?");
$success = $stmt->execute([$aircraft, $instructor, $phone]);

// Get student ID
$stmt = $conn->prepare("SELECT id FROM wp_students WHERE phone = ?");
$stmt->execute([$phone]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student) {
    $student_id = $student['id'];

    // Format amount for description (optional)
    $amount = ($aircraft * 199) + ($instructor * 95); // customize if needed
    $desc = sprintf("Purchase %.1f & %.1f - $%.2f", $aircraft, $instructor, $amount);

    // Create a ledger-style flight log entry
    $stmt = $conn->prepare("INSERT INTO wp_flight_logs 
        (student_id, total_flight_time, ground_time, description, status, appointment_type, created_at)
        VALUES (?, 0, 0, ?, 'Completed', 'Flight', NOW())");
    $stmt->execute([$student_id, $desc]);

    file_put_contents(__DIR__ . '/webhook_debug.log', "✅ Payment log created for student_id=$student_id\n", FILE_APPEND);
} else {
    file_put_contents(__DIR__ . '/webhook_debug.log', "❌ Could not find student for ledger insert\n", FILE_APPEND);
}

if ($success) {
    file_put_contents(__DIR__ . '/webhook_debug.log', "✅ Hours updated for $phone\n", FILE_APPEND);
    http_response_code(200);
} else {
    file_put_contents(__DIR__ . '/webhook_debug.log', "❌ SQL error\n", FILE_APPEND);
    http_response_code(500);
}
