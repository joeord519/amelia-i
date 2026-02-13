<?php
require 'db_connect.php';

header("Content-Type: application/json");

$phone = $_GET['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(["error" => "No phone number provided"]);
    exit;
}

// Ensure we are using the correct column name: airport_code
$stmt = $pdo->prepare("SELECT airport_code FROM wp_students WHERE phone = ?");
$stmt->execute([$phone]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if ($student && isset($student['airport_code'])) {
    echo json_encode(["airport_code" => $student['airport_code']]);
} else {
    echo json_encode(["error" => "Student not found or airport_code missing"]);
}
?>

