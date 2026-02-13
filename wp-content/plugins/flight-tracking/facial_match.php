<?php
require_once 'db.php';
header('Content-Type: application/json');

$student_id = $_POST['student_id'] ?? null;
$file = $_FILES['selfie'] ?? null;

if (!$student_id || !$file) {
  echo json_encode(['status' => 'error', 'error' => 'Missing student ID or selfie.']);
  exit;
}

// Optionally store the selfie (optional, useful for audits)
$uploadDir = __DIR__ . '/uploads/flight-selfies/';
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'solo_' . $student_id . '_' . time() . '.' . $ext;
$destination = $uploadDir . $filename;
move_uploaded_file($file['tmp_name'], $destination);

// Fake positive response
echo json_encode([
  'status' => 'success',
  'match' => true,
  'confidence' => 99.9,
  'message' => '✅ Face Recognition Approved (mock)'
]);
