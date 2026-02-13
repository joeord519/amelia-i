<?php
// /webhooks/receive_payment_webhook.php

require_once(__DIR__ . '/../db_connect.php');
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

// Optional logging for debugging
file_put_contents(__DIR__ . "/payment_debug_" . time() . ".log", json_encode($data, JSON_PRETTY_PRINT));

// Required fields
$phone     = $data['ph'] ?? null;
$aircraft  = floatval($data['aircraft_hours'] ?? 0);
$instructor = floatval($data['instructor_hours'] ?? 0);
$note      = $data['note'] ?? 'Stripe via Gravity Form';

if (!$phone || ($aircraft + $instructor) <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid data.']);
    exit;
}

// Clean phone number format to match DB
$phoneClean = preg_replace('/\D/', '', $phone);
if (strlen($phoneClean) === 10) {
    $phoneClean = '(' . substr($phoneClean, 0, 3) . ') ' . substr($phoneClean, 3, 3) . '-' . substr($phoneClean, 6);
}

try {
    $db = getDB();

    // Update hours
    $stmt = $db->prepare("
        UPDATE wp_students
        SET aircraft_hours = aircraft_hours + :aircraft,
            instructor_hours = instructor_hours + :instructor
        WHERE phone = :phone
    ");
    $stmt->execute([
        ':aircraft' => $aircraft,
        ':instructor' => $instructor,
        ':phone' => $phoneClean
    ]);

    // Optional: Log the transaction
    $log = $db->prepare("
        INSERT INTO wp_hour_transactions (phone, aircraft_hours_added, instructor_hours_added, note, created_at)
        VALUES (:phone, :aircraft, :instructor, :note, NOW())
    ");
    $log->execute([
        ':phone' => $phoneClean,
        ':aircraft' => $aircraft,
        ':instructor' => $instructor,
        ':note' => $note
    ]);

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
