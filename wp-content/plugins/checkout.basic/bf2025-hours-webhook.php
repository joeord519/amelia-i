<?php
// bf2025-hours-webhook.php
// Black Friday 2025 – 15 Aircraft + 15 Instructor hour packs

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
$db = getDB();

// Read JSON from Gravity Forms Webhook
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

// Values from GF webhook
$dealCode       = $data['deal_code']      ?? '';
$name           = trim($data['name']      ?? '');
$phoneRaw       = $data['phone']          ?? '';
$emailRaw       = $data['email']          ?? '';
$packsRequested = (int)($data['packs']    ?? 0);
$paymentAmount  = (float)($data['payment_amount'] ?? 0);
$entryId        = isset($data['entry_id']) ? (int)$data['entry_id'] : null;

// Normalize
$email = strtolower(trim($emailRaw));
$phone = preg_replace('/\D+/', '', $phoneRaw);

// Quick validations
if ($dealCode !== 'BF2025_15x15') {
    echo json_encode(['status' => 'ignored', 'message' => 'Not a Black Friday 2025 deal.']);
    exit;
}

if ($packsRequested < 1) {
    echo json_encode(['status' => 'error', 'message' => 'No packages requested.']);
    exit;
}

// 1 pack = $3,500 and 15/15 hours
$pricePerPack = 3500.00;
$hrsAircraft  = 15.0;
$hrsInstructor = 15.0;

// Sanity-check total against form calc (best-effort check)
$expected = $pricePerPack * $packsRequested;
if ($paymentAmount < $expected - 0.01) {
    echo json_encode(['status' => 'error', 'message' => 'Payment total mismatch.']);
    exit;
}

try {
    // Find student by email first
    $studentId = null;

    if ($email) {
        $stmt = $db->prepare("SELECT id FROM wp_students WHERE LOWER(email) = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$row['id'];
        }
    }

    // Try phone if no match on email
    if (!$studentId && $phone) {
        $stmt = $db->prepare("
            SELECT id
            FROM wp_students
            WHERE REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', '') LIKE :phone
            LIMIT 1
        ");
        $stmt->execute([':phone' => "%$phone%"]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $studentId = (int)$row['id'];
        }
    }

    if (!$studentId) {
        // Could not find student – log for manual follow-up, do not apply hours
        $log = $db->prepare("
            INSERT INTO wp_black_friday_2025_deals
            (student_id, customer_email, customer_phone, deal_code,
             packs_purchased, aircraft_hours_added, instructor_hours_added,
             stripe_transaction_id, raw_payload)
            VALUES (NULL, :email, :phone, :code,
                    :packs, 0, 0, NULL, :payload)
        ");
        $log->execute([
            ':email'   => $email,
            ':phone'   => $phone,
            ':code'    => $dealCode,
            ':packs'   => $packsRequested,
            ':payload' => $input,
        ]);

        echo json_encode([
            'status'  => 'error',
            'message' => 'Student not found – hours NOT applied. Needs manual review.'
        ]);
        exit;
    }

    // Enforce max 3 packs per student
    $check = $db->prepare("
        SELECT COALESCE(SUM(packs_purchased), 0) AS total_packs
        FROM wp_black_friday_2025_deals
        WHERE student_id = :sid AND deal_code = :code
    ");
    $check->execute([
        ':sid'  => $studentId,
        ':code' => $dealCode
    ]);
    $already = (int)$check->fetchColumn();

    $remainingAllowed = 3 - $already;
    if ($remainingAllowed <= 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Max of 3 Black Friday packs already purchased. No additional hours applied.'
        ]);
        exit;
    }

    // Cap packs to remaining allowed
    $packsToApply = min($packsRequested, $remainingAllowed);

    $addAircraft   = $packsToApply * $hrsAircraft;
    $addInstructor = $packsToApply * $hrsInstructor;

    // Update balances
    $update = $db->prepare("
        UPDATE wp_students
        SET aircraft_hours_balance   = aircraft_hours_balance + :a,
            instructor_hours_balance = instructor_hours_balance + :i
        WHERE id = :sid
    ");
    $update->execute([
        ':a'   => $addAircraft,
        ':i'   => $addInstructor,
        ':sid' => $studentId
    ]);

    // Log the transaction
    $log = $db->prepare("
        INSERT INTO wp_black_friday_2025_deals
        (student_id, customer_email, customer_phone, deal_code,
         packs_purchased, aircraft_hours_added, instructor_hours_added,
         stripe_transaction_id, raw_payload)
        VALUES (:sid, :email, :phone, :code,
                :packs, :aircraft, :instructor, NULL, :payload)
    ");
    $log->execute([
        ':sid'       => $studentId,
        ':email'     => $email,
        ':phone'     => $phone,
        ':code'      => $dealCode,
        ':packs'     => $packsToApply,
        ':aircraft'  => $addAircraft,
        ':instructor'=> $addInstructor,
        ':payload'   => $input,
    ]);

    echo json_encode([
        'status'          => 'success',
        'student_id'      => $studentId,
        'packs_requested' => $packsRequested,
        'packs_applied'   => $packsToApply,
        'aircraft_added'  => $addAircraft,
        'instructor_added'=> $addInstructor,
        'packs_remaining' => 3 - ($already + $packsToApply),
        'entry_id'        => $entryId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
