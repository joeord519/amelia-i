<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

$phone = $_GET['phone'] ?? '';
if (!$phone) {
  error_log("Phone not provided");
  echo json_encode(['success' => false, 'message' => 'Phone required']);
  exit;
}

try {
  $db = getDB();
  $clean = preg_replace('/\D/', '', $phone);
  error_log("Cleaned phone: " . $clean);

  // Search wp_cfis
  $stmt = $db->prepare("
    SELECT cfi_id, first_name, last_name, phone
    FROM wp_cfis
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
    AND status = 'Active'
  ");
  $stmt->execute([$clean]);
  $cfi = $stmt->fetch();

  if ($cfi) {
    $_SESSION['user_role'] = 'cfi';
    $_SESSION['user_name'] = "{$cfi['first_name']} {$cfi['last_name']}";
    $_SESSION['user_phone'] = $cfi['phone'];
    $_SESSION['user_cfi_id'] = $cfi['cfi_id'];

    error_log("CFI login success: " . json_encode($_SESSION));

    echo json_encode([
      'success' => true,
      'role' => 'cfi',
      'name' => $_SESSION['user_name'],
      'phone' => $_SESSION['user_phone'],
      'cfi_id' => $_SESSION['user_cfi_id']
    ]);
    exit;
  }

  // Search wp_admin_users
  $stmt = $db->prepare("
    SELECT name, phone
    FROM wp_admin_users
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') = ?
  ");
  $stmt->execute([$clean]);
  $admin = $stmt->fetch();

  if ($admin) {
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_name'] = $admin['name'];
    $_SESSION['user_phone'] = $admin['phone'];

    error_log("Admin login success: " . json_encode($_SESSION));

    echo json_encode([
      'success' => true,
      'role' => 'admin',
      'name' => $_SESSION['user_name'],
      'phone' => $_SESSION['user_phone']
    ]);
    exit;
  }

  error_log("No matching user found");
  echo json_encode(['success' => false, 'message' => 'Not found']);

} catch (Exception $e) {
  error_log("Login error: " . $e->getMessage());
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}



