<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $cleanedPhone = preg_replace('/[^0-9]/', '', $input['phone']); // Strip to digits

    if (!$cleanedPhone || strlen($cleanedPhone) < 10) {
        throw new Exception("Invalid or missing phone number.");
    }

    // Clean the phone in SQL by removing common characters
    $stmt = $pdo->prepare("
        SELECT * FROM wp_students 
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '.', '') = ?
    ");
    $stmt->execute([$cleanedPhone]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        http_response_code(404);
        echo json_encode(['status' => 'not_found', 'error' => 'Student not found']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'student' => [
            'student_id' => $student['student_id'],
            'first_name' => $student['first_name'],
            'last_name' => $student['last_name'],
            'profile_photo_url' => $student['profile_photo_url'],
            'airport_code' => $student['airport_code'] ?? null,
            'aircraft_hours_remaining' => $student['aircraft_hours_remaining'],
            'instructor_hours_remaining' => $student['instructor_hours_remaining']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
