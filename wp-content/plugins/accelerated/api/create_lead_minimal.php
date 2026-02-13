<?php
// Allow cross-origin POSTs from flypiston.com
header("Access-Control-Allow-Origin: https://flypiston.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once('../db_connect.php');

try {
  $db = getDB();

  // Sanitize and extract POST
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone']);

  if (!$name || !$email || !$phone) {
    throw new Exception("Missing required fields");
  }

  // Split name into first/last
  $first_name = '';
  $last_name = '';
  if (strpos($name, ' ') !== false) {
    [$first_name, $last_name] = explode(' ', $name, 2);
  } else {
    $first_name = $name;
    $last_name = '';
  }

  // Check for existing lead
  $stmt = $db->prepare("SELECT id FROM wp_leads WHERE email = :email LIMIT 1");
  $stmt->execute(['email' => $email]);
  $existing = $stmt->fetchColumn();

  if ($existing) {
    echo json_encode(['id' => $existing]);
    exit;
  }

  // Insert new lead
  $stmt = $db->prepare("INSERT INTO wp_leads (first_name, last_name, email, phone, training_program, created_at)
    VALUES (:first_name, :last_name, :email, :phone, :training_program, NOW())");

  $stmt->execute([
    'first_name' => $first_name,
    'last_name' => $last_name,
    'email' => $email,
    'phone' => $phone,
    'training_program' => 'Accelerated PPL'
  ]);

  echo json_encode(['id' => $db->lastInsertId()]);
} catch (Exception $e) {
  echo json_encode(['error' => $e->getMessage()]);
}
