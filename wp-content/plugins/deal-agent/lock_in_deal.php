<?php
// lock_in_deal.php – validate negotiated deal + create deal session
// Stripe integration will replace the placeholder checkout_url later.

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

try {
    $pdo = getDB();

    // Read JSON body
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid JSON body',
        ]);
        exit;
    }

    $studentId            = isset($data['student_id']) ? (int) $data['student_id'] : 0;
    $dealCode             = isset($data['deal_code']) ? trim($data['deal_code']) : '';
    $finalPrice           = isset($data['final_price']) ? (float) $data['final_price'] : 0;
    $discountPct          = isset($data['discount_pct']) ? (float) $data['discount_pct'] : 0;
    $bonusAircraftHours   = isset($data['bonus_aircraft_hours']) ? (float) $data['bonus_aircraft_hours'] : 0;
    $bonusInstructorHours = isset($data['bonus_instructor_hours']) ? (float) $data['bonus_instructor_hours'] : 0;

    if ($studentId <= 0 || $dealCode === '' || $finalPrice <= 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'Missing or invalid required fields',
        ]);
        exit;
    }

    // 1) Fetch student – NOTE: using student_id column, and NOT selecting `name`
    $sqlStudent = "
        SELECT student_id, financially_grounded, banned
        FROM wp_students
        WHERE student_id = :student_id
        LIMIT 1
    ";
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

    if (!empty($student['banned'])) {
        echo json_encode([
            'success' => false,
            'error'   => 'Student is banned; cannot lock in deal',
        ]);
        exit;
    }

    // 2) Fetch deal rule
    $sqlRule = "
        SELECT *
        FROM wp_deal_rules
        WHERE deal_code = :deal_code
          AND is_active = 1
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sqlRule);
    $stmt->execute([':deal_code' => $dealCode]);
    $rule = $stmt->fetch();

    if (!$rule) {
        echo json_encode([
            'success' => false,
            'error'   => 'Deal rule not found or inactive',
        ]);
        exit;
    }

    $basePrice               = (float) $rule['base_price'];
    $minDiscountPct          = (float) $rule['min_discount_pct'];
    $maxDiscountPct          = (float) $rule['max_discount_pct'];
    $maxBonusAircraftHours   = (float) $rule['max_bonus_aircraft_hours'];
    $maxBonusInstructorHours = (float) $rule['max_bonus_instructor_hours'];

    // 3) Validate discount & bonuses within allowed ranges
    if ($discountPct < $minDiscountPct || $discountPct > $maxDiscountPct) {
        echo json_encode([
            'success' => false,
            'error'   => 'Discount percentage out of allowed range',
        ]);
        exit;
    }

    if ($bonusAircraftHours < 0 || $bonusAircraftHours > $maxBonusAircraftHours) {
        echo json_encode([
            'success' => false,
            'error'   => 'Bonus aircraft hours out of allowed range',
        ]);
        exit;
    }

    if ($bonusInstructorHours < 0 || $bonusInstructorHours > $maxBonusInstructorHours) {
        echo json_encode([
            'success' => false,
            'error'   => 'Bonus instructor hours out of allowed range',
        ]);
        exit;
    }

    // Optional rule: final_price <= base_price (you can loosen this later)
    if ($finalPrice > $basePrice) {
        echo json_encode([
            'success' => false,
            'error'   => 'Final price cannot exceed base price for this deal',
        ]);
        exit;
    }

    // 4) Insert into wp_deal_sessions
    $sqlInsert = "
        INSERT INTO wp_deal_sessions
            (student_id, deal_code, base_price, final_price,
             discount_pct, bonus_aircraft_hours, bonus_instructor_hours,
             status, created_at, updated_at)
        VALUES
            (:student_id, :deal_code, :base_price, :final_price,
             :discount_pct, :bonus_aircraft_hours, :bonus_instructor_hours,
             'offered', NOW(), NOW())
    ";
    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        ':student_id'             => $studentId,
        ':deal_code'              => $dealCode,
        ':base_price'             => $basePrice,
        ':final_price'            => $finalPrice,
        ':discount_pct'           => $discountPct,
        ':bonus_aircraft_hours'   => $bonusAircraftHours,
        ':bonus_instructor_hours' => $bonusInstructorHours,
    ]);

    $dealSessionId = (int) $pdo->lastInsertId();

    // 5) Placeholder checkout URL (Stripe will replace this later)
    $checkoutUrl = 'https://amelia-i.com/deal-checkout-placeholder.php?deal_session_id=' . $dealSessionId;

    $sqlUpdate = "
        UPDATE wp_deal_sessions
        SET checkout_url = :checkout_url,
            status       = 'checkout_created',
            updated_at   = NOW()
        WHERE id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sqlUpdate);
    $stmt->execute([
        ':checkout_url' => $checkoutUrl,
        ':id'           => $dealSessionId,
    ]);

    echo json_encode([
        'success'         => true,
        'deal_session_id' => $dealSessionId,
        'checkout_url'    => $checkoutUrl,
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


