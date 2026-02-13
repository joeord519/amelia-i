<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php';
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

ob_start(); // Prevents any extra output

$mobile = $_POST['mobile'] ?? '';
$security_word = $_POST['security_word'] ?? '';

if (empty($mobile) || empty($security_word)) {
    echo json_encode(['status' => 'error', 'message' => 'Mobile number and security word are required.']);
    exit;
}

// ✅ Query the database
$query = "SELECT student_id FROM wp_students WHERE phone = ? AND security_word = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $mobile, $security_word);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid mobile number or security word.']);
    exit;
}

// ✅ User Verified, Send Success
echo json_encode(['status' => 'success']);
exit;

