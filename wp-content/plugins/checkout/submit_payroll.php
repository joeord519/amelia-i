<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');
$conn = getDB();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $cfi_id = intval($data['cfi_id']);
    $amount = floatval($data['amount']);
    $check_number = trim($data['check_number']);
    $type = $data['type'] ?? 'full';
    $notes = trim($data['notes']) ?? '';

    if (!$cfi_id || !$amount || !$check_number) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
        exit;
    }

    // Get current cutoff date
    $stmt = $conn->prepare("SELECT setting_value FROM wp_company_settings WHERE setting_key = 'cfi_pay_cutoff_date'");
    $stmt->execute();
    $cutoff = $stmt->fetchColumn();

    // Insert payment record
    $insert = $conn->prepare("
        INSERT INTO wp_cfi_payments (cfi_id, pay_date, amount, pay_period_start, pay_period_end, notes)
        VALUES (:cfi_id, NOW(), :amount, :start, CURDATE(), :notes)
    ");
    $insert->execute([
        ':cfi_id' => $cfi_id,
        ':amount' => $amount,
        ':start' => $cutoff,
        ':notes' => "Check #: $check_number | " . $notes
    ]);

    // Only reset the cutoff if payment is full
    if ($type === 'full') {
        $update = $conn->prepare("
            UPDATE wp_company_settings SET setting_value = CURDATE() WHERE setting_key = 'cfi_pay_cutoff_date'
        ");
        $update->execute();
    }

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
