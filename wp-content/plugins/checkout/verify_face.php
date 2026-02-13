<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

// Azure config
$azureEndpoint = "https://piston-face-api.cognitiveservices.azure.com/";
$azureKey = "FoXwTOlGdzh9WfGPUXm6V6HQaYkLwqYJe9CKrWlHs7guFit4KInYJQQJ99BDACYeBjFXJ3w3AAAKACOGvcwA";

// Inputs
$student_id = $_POST['student_id'] ?? '';
$file = $_FILES['live_selfie'] ?? null;

if (!$student_id || !$file || $file['error'] !== 0) {
  echo json_encode(['status' => 'error', 'message' => 'Missing selfie or student ID']);
  exit;
}

// Save live selfie
$upload_dir = __DIR__ . '/uploads/';
if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

$filename = 'live_' . time() . '_' . basename($file['name']);
$local_path = $upload_dir . $filename;
$web_path = '/wp-content/plugins/checkout/uploads/' . $filename;
$full_url = 'https://amelia-i.com' . $web_path;

if (!move_uploaded_file($file['tmp_name'], $local_path)) {
  echo json_encode(['status' => 'error', 'message' => 'Failed to save selfie file']);
  exit;
}

// Confirm file exists after save
if (!file_exists($local_path)) {
  echo json_encode(['status' => 'error', 'message' => 'Selfie upload failed (file missing)']);
  exit;
}

// DB + student profile lookup
$conn = getDB();
$stmt = $conn->prepare("SELECT profile_photo_url, phone FROM wp_students WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || empty($student['profile_photo_url'])) {
  echo json_encode(['status' => 'error', 'message' => 'No profile photo found']);
  exit;
}

$profile_url = $student['profile_photo_url'];
if (!str_starts_with($profile_url, 'http')) {
  $profile_url = 'https://amelia-i.com' . $profile_url;
}

// Get Azure Face IDs
$faceId1 = getFaceId($profile_url, $azureEndpoint, $azureKey);
$faceId2 = getFaceId($full_url, $azureEndpoint, $azureKey);

if (!$faceId1 || !$faceId2) {
  echo json_encode([
    'status' => 'error',
    'message' => 'Face not detected',
    'debug' => [
      'profile_url' => $profile_url,
      'selfie_url' => $full_url,
      'faceId1' => $faceId1,
      'faceId2' => $faceId2
    ]
  ]);
  exit;
}

// Compare faces using Azure Verify API
$verify = curl_init($azureEndpoint . "face/v1.0/verify");
curl_setopt_array($verify, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Ocp-Apim-Subscription-Key: $azureKey"
  ],
  CURLOPT_POSTFIELDS => json_encode([
    'faceId1' => $faceId1,
    'faceId2' => $faceId2
  ])
]);
$response = curl_exec($verify);
curl_close($verify);

// Decode Azure response
$azureResult = json_decode($response, true);

if (!$azureResult || !isset($azureResult['confidence'])) {
  echo json_encode([
    'status' => 'error',
    'message' => 'Invalid Azure response',
    'debug' => $azureResult
  ]);
  exit;
}

$confidence = $azureResult['confidence'] ?? 0;

// ✅ Match Success
if ($confidence >= 0.85) {
  echo json_encode([
    'status' => 'match',
    'confidence' => $confidence,
    'phone' => $student['phone'],
    'photo' => $web_path
  ]);
  exit;
}

// ❌ Log mismatch
$log = $conn->prepare("INSERT INTO wp_face_mismatch_flags (student_id, selfie_attempt_url) VALUES (?, ?)");
$log->execute([$student_id, $web_path]);

echo json_encode([
  'status' => 'mismatch',
  'confidence' => $confidence,
  'photo' => $web_path
]);
exit;

// 🔁 Azure Face Detection Helper
function getFaceId($imageUrl, $endpoint, $key) {
  $ch = curl_init($endpoint . "face/v1.0/detect?returnFaceId=true");
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "Ocp-Apim-Subscription-Key: $key"
    ],
    CURLOPT_POSTFIELDS => json_encode(['url' => $imageUrl])
  ]);
  $result = curl_exec($ch);
  curl_close($ch);
  $json = json_decode($result, true);
  return $json[0]['faceId'] ?? null;
}
