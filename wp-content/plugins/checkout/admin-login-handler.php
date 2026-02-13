<?php
session_start();
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// STEP 1: Wrap DB connect
try {
  require_once __DIR__ . '/db_connect.php';
  $conn = getDB();
} catch (Throwable $e) {
  echo json_encode([
    "success" => false,
    "message" => "Database connection failed: " . $e->getMessage()
  ]);
  exit;
}

// STEP 2: Validate login request
$data = json_decode(file_get_contents("php://input"), true);
$phone = $data['phone'] ?? '';
$pass = $data['pass'] ?? '';

// STEP 3: Look up admin in database
try {
  $stmt = $conn->prepare("SELECT * FROM wp_admin_users WHERE phone = ? LIMIT 1");
  $stmt->execute([$phone]);
  $admin = $stmt->fetch();

  if ($admin && $admin['password'] === $pass) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_phone'] = $admin['phone'];
    $_SESSION['admin_email'] = $admin['email'] ?? '';
    $_SESSION['admin_id'] = $admin['id'];

    echo json_encode(["success" => true]);
  } else {
    echo json_encode(["success" => false, "message" => "Invalid phone or password."]);
  }
} catch (Throwable $e) {
  echo json_encode(["success" => false, "message" => "Query error: " . $e->getMessage()]);
}




