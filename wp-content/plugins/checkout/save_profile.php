<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$student_id = $_POST['student_id'] ?? '';
$file = $_FILES['selfie'] ?? null;

if (!$student_id || !$file || $file['error'] !== 0) {
  echo json_encode(['status' => 'error', 'message' => 'Missing selfie or student ID']);
  exit;
}

$upload_dir = __DIR__ . '/uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

$filename = 'profile_' . time() . '_' . basename($file['name']);
$local_path = $upload_dir . $filename;
$relative_path = '/wp-content/plugins/checkout/uploads/' . $filename;
$full_url = 'https://amelia-i.com' . $relative_path;

move_uploaded_file($file['tmp_name'], $local_path);

// Save FULL URL to DB
$conn = getDB();
$stmt = $conn->prepare("UPDATE wp_students SET profile_photo_url = ? WHERE student_id = ?");
$stmt->execute([$full_url, $student_id]);

echo json_encode([
  'status' => 'success',
  'url' => $full_url
]);
