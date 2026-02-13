<?php
require_once __DIR__ . '/db_connect.php';
$conn = getDB();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$response = [];

foreach ($data as $entry) {
    $cfi_id = intval($entry['cfi_id']);
    $amount = floatval($entry['amount']);
    $notes = $entry['notes'] ?? null;

    // Get unpaid logs for this CFI
    $stmt = $conn->prepare("
        SELECT id, flight_date
        FROM wp_flight_logs
        WHERE cfi_id = :cfi_id AND cfi_payment_id IS NULL
        ORDER BY flight_date ASC
    ");
    $stmt->execute([':cfi_id' => $cfi_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($logs) === 0) {
        $response[] = [
            'cfi_id' => $cfi_id,
            'status' => 'skipped',
            'message' => 'No unpaid logs found'
        ];
        continue;
    }

    $pay_period_start = $logs[0]['flight_date'];
    $pay_period_end = $logs[count($logs) - 1]['flight_date'];
    $entry_count = count($logs);

    // Insert payment record
    $stmt = $conn->prepare("
        INSERT INTO wp_cfi_payments
        (cfi_id, amount, pay_date, pay_period_start, pay_period_end, notes, entry_count, created_at)
        VALUES (:cfi_id, :amount, NOW(), :start, :end, :notes, :count, NOW())
    ");
    $stmt->execute([
        ':cfi_id' => $cfi_id,
        ':amount' => $amount,
        ':start' => $pay_period_start,
        ':end' => $pay_period_end,
        ':notes' => $notes,
        ':count' => $entry_count
    ]);

    $payment_id = $conn->lastInsertId();

    // Update the flight logs with this payment ID
    $stmt = $conn->prepare("
        UPDATE wp_flight_logs
        SET cfi_payment_id = :payment_id
        WHERE cfi_id = :cfi_id AND cfi_payment_id IS NULL
          AND flight_date BETWEEN :start AND :end
    ");
    $stmt->execute([
        ':payment_id' => $payment_id,
        ':cfi_id' => $cfi_id,
        ':start' => $pay_period_start,
        ':end' => $pay_period_end
    ]);

    $response[] = [
        'cfi_id' => $cfi_id,
        'payment_id' => $payment_id,
        'status' => 'paid',
        'entries_updated' => $entry_count,
        'amount' => $amount
    ];
}

echo json_encode($response);
?>
