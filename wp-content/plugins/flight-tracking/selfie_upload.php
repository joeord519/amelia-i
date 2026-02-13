<?php
require_once 'db.php';
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/flight-selfies/';
$student_id = $_POST['student_id'] ?? null;
$file = $_FILES['selfie'] ?? null;

if (!$student_id || !$file) {
  echo json_encode(['status' => 'error', 'error' => 'Missing student ID or selfie file']);
  exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
  echo json_encode(['status' => 'error', 'error' => 'Invalid file type']);
  exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['status' => 'error', 'error' => 'Upload error: ' . $file['error']]);
  exit;
}

// Ensure directory exists
if (!file_exists($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}

// Create unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
$destination = $uploadDir . $filename;

// Save selfie file
if (!move_uploaded_file($file['tmp_name'], $destination)) {
  echo json_encode(['status' => 'error', 'error' => 'Failed to save selfie']);
  exit;
}

// ✅ Save full public URL to database (Azure expects this format)
$relativePath = 'wp-content/plugins/flight-tracking/uploads/flight-selfies/' . $filename;
$fullUrl = 'https://amelia-i.com/' . $relativePath;

try {
  $stmt = $pdo->prepare("UPDATE wp_students SET profile_photo_url = ? WHERE student_id = ?");
  $stmt->execute([$fullUrl, $student_id]);

  echo json_encode([
    'status' => 'success',
    'photo_url' => $fullUrl
  ]);
} catch (PDOException $e) {
  echo json_encode(['status' => 'error', 'error' => 'DB error: ' . $e->getMessage()]);
}

