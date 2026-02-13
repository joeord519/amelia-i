<?php
// /wp-content/plugins/accelerated/register_accelerated.php
require_once('db_connect.php');
header('Content-Type: application/json');

// Sanitize input
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$selected_month = trim($_POST['selected_month'] ?? '');
$housing_choice = trim($_POST['housing_choice'] ?? 'Shared');

if (!$full_name || !$email || !$phone || !$selected_month) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields.']);
    exit;
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("INSERT INTO wp_leads 
        (full_name, email, phone, lead_source, selected_month, housing_choice, deposit_paid, stripe_checkout_id, created_at) 
        VALUES (?, ?, ?, 'Accelerated PPL', ?, ?, 0, NULL, NOW())");

    $stmt->execute([
        $full_name,
        $email,
        $phone,
        $selected_month,
        $housing_choice
    ]);

    $lead_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Lead registered.',
        'lead_id' => $lead_id
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

