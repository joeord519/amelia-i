<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/generate_receipt.php');

header('Content-Type: application/json');

$student_id        = intval($_POST['student_id'] ?? 0);
$aircraft_hours    = floatval($_POST['aircraft_hours'] ?? 0);
$instructor_hours  = floatval($_POST['instructor_hours'] ?? 0);
$amount            = floatval($_POST['amount'] ?? 0);
$method            = $_POST['method'] ?? 'manual';  // cash, check, manual, etc
$coupon_code       = $_POST['coupon_code'] ?? null;
$purchased_by      = $_POST['purchased_by'] ?? 'admin';  // or 'cfi'

// Validate
if (!$student_id || $amount <= 0 || ($aircraft_hours + $instructor_hours) <= 0) {
  echo json_encode(['success' => false, 'message' => 'Missing or invalid fields.']);
  exit;
}

try {
  $db = getDB();
  $db->beginTransaction();

  // Update student balances
  $stmt = $db->prepare("UPDATE wp_students SET aircraft_hours_remaining = aircraft_hours_remaining + ?, instructor_hours_remaining = instructor_hours_remaining + ? WHERE student_id = ?");
  $stmt->execute([$aircraft_hours, $instructor_hours, $student_id]);

  // Insert payment log
  $stmt = $db->prepare("
    INSERT INTO wp_payments (student_id, method, amount, aircraft_hours, instructor_hours, coupon_code, purchased_by) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $student_id,
    $method,
    $amount,
    $aircraft_hours,
    $instructor_hours,
    $coupon_code,
    $purchased_by
  ]);

  $payment_id = $db->lastInsertId();

  // Generate receipt
  $receipt_path = generate_receipt($payment_id);
  $stmt = $db->prepare("UPDATE wp_payments SET receipt_file = ? WHERE id = ?");
  $stmt->execute([$receipt_path, $payment_id]);

  $db->commit();
  echo json_encode(['success' => true, 'receipt_url' => $receipt_path]);

} catch (Exception $e) {
  $db->rollBack();
  echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
