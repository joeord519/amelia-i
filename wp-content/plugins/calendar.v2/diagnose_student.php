<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

// === CONFIG ===
$REQUIRED_AIRCRAFT_HOURS = 1.5;
$REQUIRED_INSTRUCTOR_HOURS = 2.0;
$RECENCY_DAYS_LIMIT = 90;  // adjust as needed

try {
    $conn = getDB();
    $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

    if (!$student_id) {
        echo json_encode(['error' => 'No student_id provided']);
        exit;
    }

    // === 1. Get student record ===
    $stmt = $conn->prepare("SELECT * FROM wp_students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }

    // === 2. Check recent flight ===
    $stmt = $conn->prepare("SELECT MAX(start_time) as last_flight FROM wp_flight_schedule WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $last_flight = $stmt->fetchColumn();
    $last_flight_date = $last_flight ? new DateTime($last_flight) : null;

    $now = new DateTime();
    $recent_enough = $last_flight_date ? $last_flight_date >= (clone $now)->modify("-{$RECENCY_DAYS_LIMIT} days") : false;

    // === 3. Check future flights today ===
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM wp_flight_schedule
        WHERE student_id = ? AND DATE(start_time) = CURDATE()
    ");
    $stmt->execute([$student_id]);
    $has_flight_today = $stmt->fetchColumn() > 0;

    // === 4. Build output ===
    $result = [
        'student_id' => $student_id,
        'name' => $student['first_name'] . ' ' . $student['last_name'],
        'aircraft_hours' => floatval($student['aircraft_hours_available']),
        'instructor_hours' => floatval($student['instructor_hours_available']),
        'latest_contract_file' => $student['latest_contract_file'],
        'banned' => boolval($student['banned']),
        'last_flight' => $last_flight,
        'recent_enough' => $recent_enough,
        'has_flight_today' => $has_flight_today,
        'checks' => [
            '✅ Contract Signed' => $student['latest_contract_file'] && strtolower($student['latest_contract_file']) !== 'no',
            '✅ Not Banned' => !$student['banned'],
            '✅ Enough Aircraft Time' => $student['aircraft_hours_available'] >= $REQUIRED_AIRCRAFT_HOURS,
            '✅ Enough Instructor Time' => $student['instructor_hours_available'] >= $REQUIRED_INSTRUCTOR_HOURS,
            '✅ Recent Flight (within ' . $RECENCY_DAYS_LIMIT . ' days)' => $recent_enough,
            '✅ Not Double-Booked Today' => !$has_flight_today
        ]
    ];

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
