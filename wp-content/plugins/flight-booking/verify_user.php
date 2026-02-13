<?php
require_once __DIR__ . '/db_connect.php'; // ✅ Ensure database connection

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$mobile = $_POST['mobile'] ?? '';
$security_word = $_POST['security_word'] ?? '';

if (empty($mobile) || empty($security_word)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing mobile number or security word']);
    exit;
}

// ✅ Fetch student record based on phone number
$query = "SELECT student_id, first_name, last_name FROM wp_students WHERE phone = ? AND security_word = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("ss", $mobile, $security_word);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found or incorrect security word']);
    exit;
}

$student = $result->fetch_assoc();
$stmt->close();

// ✅ Return student ID
echo json_encode([
    'status' => 'success',
    'student_id' => $student['student_id'], // ✅ Ensure this is returned
    'student_name' => $student['first_name'] . ' ' . $student['last_name']
]);
?>




