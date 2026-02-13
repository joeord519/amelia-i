<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

// Get posted data
$data = json_decode(file_get_contents("php://input"), true);

$student_id = $data['student_id'] ?? '';
$security_word = trim($data['security_word'] ?? '');

if (!$student_id || !$security_word) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

try {
    $conn = getDB();
    $stmt = $conn->prepare("UPDATE wp_students SET security_word = ? WHERE student_id = ?");
    $stmt->execute([$security_word, $student_id]);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
}
?>

