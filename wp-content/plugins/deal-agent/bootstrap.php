<?php
// bootstrap.php – returns student profile + eligible deals for Joey

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

try {
    $pdo = getDB();

    $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
    if ($studentId <= 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'Missing or invalid student_id',
        ]);
        exit;
    }

    // 1) Get the student row – NOTE: using student_id
    $sqlStudent = "SELECT * FROM wp_students WHERE student_id = :student_id LIMIT 1";
    $stmt = $pdo->prepare($sqlStudent);
    $stmt->execute([':student_id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode([
            'success' => false,
            'error'   => 'Student not found',
        ]);
        exit;
    }

    // 2) Determine segment + program
    $segment = !empty($student['deal_segment']) ? $student['deal_segment'] : 'GENERAL';
    $program = !empty($student['program']) ? $student['program'] : null;

    // 3) Fetch eligible deals
    if ($program) {
        $sqlDeals = "
            SELECT id, segment, program, deal_code, label,
                   base_price, min_discount_pct, max_discount_pct,
                   max_bonus_aircraft_hours, max_bonus_instructor_hours,
                   allow_payment_plan
            FROM wp_deal_rules
            WHERE is_active = 1
              AND segment = :segment
              AND (program = :program OR program IS NULL)
        ";
        $stmtDeals = $pdo->prepare($sqlDeals);
        $stmtDeals->execute([
            ':segment' => $segment,
            ':program' => $program,
        ]);
    } else {
        $sqlDeals = "
            SELECT id, segment, program, deal_code, label,
                   base_price, min_discount_pct, max_discount_pct,
                   max_bonus_aircraft_hours, max_bonus_instructor_hours,
                   allow_payment_plan
            FROM wp_deal_rules
            WHERE is_active = 1
              AND segment = :segment
        ";
        $stmtDeals = $pdo->prepare($sqlDeals);
        $stmtDeals->execute([
            ':segment' => $segment,
        ]);
    }

    $deals = $stmtDeals->fetchAll();

    echo json_encode([
        'success' => true,
        'student' => $student,
        'deals'   => $deals,
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'DB error: ' . $e->getMessage(),
    ]);
    exit;
}



