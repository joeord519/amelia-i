<?php
// fetch_student_balance.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../db_connect.php');
$db = getDB();

header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
$phone = preg_replace('/\D/', '', $phone); // digits only

if (strlen($phone) !== 10) {
    echo json_encode(['error' => 'Invalid phone number']);
    exit;
}

// Format to match how it's stored in wp_students (e.g. "(636) 328-3750")
$formattedPhone = sprintf("(%s) %s-%s",
    substr($phone, 0, 3),
    substr($phone, 3, 3),
    substr($phone, 6)
);

try {
    $stmt = $db->prepare("
        SELECT
            student_id,
            first_name,
            last_name,
            email,
            phone,
            aircraft_hours_remaining,
            instructor_hours_remaining,
            installment_price,
            phase_pay,
            installments_remaining,
            installment_flight_hours,
            installment_instructor_hours,
            latest_contract_signed,
            latest_contract_signed_at
        FROM wp_students
        WHERE phone = ?
        LIMIT 1
    ");
    $stmt->execute([$formattedPhone]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    echo json_encode([
        'student_id'         => (int)($student['student_id'] ?? 0),
        'name'               => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
        'email'              => $student['email'] ?? '',
        'phone'              => $student['phone'] ?? $formattedPhone,

        'phase'              => $student['phase_pay'] ?? 'No',
        'ileft'              => (int)($student['installments_remaining'] ?? 0),
        'cab'                => (float)($student['aircraft_hours_remaining'] ?? 0),
        'cib'                => (float)($student['instructor_hours_remaining'] ?? 0),
        'iah'                => (float)($student['installment_flight_hours'] ?? 0),
        'iih'                => (float)($student['installment_instructor_hours'] ?? 0),
        'installment_price'  => (float)($student['installment_price'] ?? 0),

        // ✅ New: Contract gate fields
        'latest_contract_signed'    => (int)($student['latest_contract_signed'] ?? 0),
        'latest_contract_signed_at' => $student['latest_contract_signed_at'] ?? null,
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}


