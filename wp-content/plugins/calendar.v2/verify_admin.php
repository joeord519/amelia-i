<?php
// Enable debug mode for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('db_connect.php');
$conn = getDB();
header('Content-Type: application/json');

// Read raw POST input and log for debugging
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

// Optional: log the incoming raw JSON payload
error_log("📥 Admin Login Payload: " . $rawInput);

$phone = $data['phone'] ?? '';
$password = $data['pass'] ?? '';

if (!$phone || !$password) {
  echo json_encode(["success" => false, "message" => "Missing phone or password."]);
  exit;
}

try {
  $stmt = $conn->prepare("SELECT * FROM wp_admin_users WHERE phone = ?");
  $stmt->execute([$phone]);
  $admin = $stmt->fetch(PDO::FETCH_ASSOC);

  // TEMP: Plaintext password match (replace with password_verify later)
  if (!$admin || $password !== $admin['password']) {
    echo json_encode(["success" => false, "message" => "Invalid credentials."]);
    exit;
  }

  echo json_encode([
    "success" => true,
    "name" => $admin['name'],
    "email" => $admin['email']
  ]);
} catch (Exception $e) {
  error_log("❌ Admin login error: " . $e->getMessage());
  echo json_encode(["success" => false, "message" => "Server error."]);
}
