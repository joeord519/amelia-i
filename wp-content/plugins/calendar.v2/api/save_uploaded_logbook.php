<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../db_connect.php');
$db = getDB();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$flight_log_id = intval($input['flight_log_id'] ?? 0);
$file_url = trim($input['file_url'] ?? '');
$file_type = trim($input['file_type'] ?? '');

if (!$flight_log_id || !$file_url) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required input.']);
  exit;
}

try {
  $stmt = $db->prepare("INSERT INTO wp_logbook_uploads (flight_log_id, file_url, file_type) VALUES (?, ?, ?)");
  $success = $stmt->execute([$flight_log_id, $file_url, $file_type]);

  echo json_encode(['success' => $success]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
