<?php
require 'db_connect.php';

header("Content-Type: application/json");

$phone = $_GET['phone'] ?? '';

if (!$phone) {
    echo json_encode(["error" => "Phone number missing"]);
    exit;
}

// Fetch assigned CFI
$stmt = $pdo->prepare("
    SELECT cf.first_name, cf.last_name
    FROM wp_students s
    JOIN wp_cfis cf ON s.assigned_cfi_id = cf.cfi_id
    WHERE s.phone = ?
");
$stmt->execute([$phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $assigned_cfi = $user['first_name'] . ' ' . $user['last_name'];
    echo json_encode(["assigned_cfi" => $assigned_cfi]);
} else {
    echo json_encode(["assigned_cfi" => "N/A"]);
}
?>



