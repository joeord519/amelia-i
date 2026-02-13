<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

$inputPhone = $_GET['phone'] ?? '';
if (!$inputPhone) {
  echo json_encode(['success' => false, 'message' => 'Missing phone']);
  exit;
}

try {
  $db = getDB();

  // 1. Try CFI login
  $stmt = $db->prepare("SELECT first_name, last_name FROM wp_cfis WHERE phone = ? AND status = 'Active' LIMIT 1");
  $stmt->execute([$inputPhone]);
  $row = $stmt->fetch();

  if ($row) {
    $name = trim($row['first_name'] . ' ' . $row['last_name']);
    echo json_encode([
      'success' => true,
      'name' => $name,
      'phone' => $inputPhone,
      'role' => 'cfi'
    ]);
    exit;
  }

  // 2. Try Admin login
  $stmt = $db->prepare("SELECT name FROM wp_admin_users WHERE phone = ? LIMIT 1");
  $stmt->execute([$inputPhone]);
  $admin = $stmt->fetch();

  if ($admin) {
    echo json_encode([
      'success' => true,
      'name' => $admin['name'],
      'phone' => $inputPhone,
      'role' => 'admin'
    ]);
    exit;
  }

  echo json_encode(['success' => false]);

} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
