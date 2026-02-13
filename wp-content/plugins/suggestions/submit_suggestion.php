<?php
require_once(__DIR__ . '/db_connect.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['suggestion']) || trim($data['suggestion']) === '') {
  echo json_encode(['success' => false, 'message' => 'Invalid input']);
  exit;
}

try {
  $db = getDB();
  $stmt = $db->prepare("
    INSERT INTO wp_suggestions (category, suggestion, name, email, phone, submitted_at)
    VALUES (?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    $data['category'] ?? '',
    $data['suggestion'],
    $data['name'] ?? '',
    $data['email'] ?? '',
    $data['phone'] ?? '',
  ]);
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
