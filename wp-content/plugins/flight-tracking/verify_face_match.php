<?php
header('Content-Type: application/json');

// Azure config
$subscriptionKey = 'FoXwTOlGdzh9WfGPUXm6V6HQaYkLwqYJe9CKrWlHs7guFit4KInYJQQJ99BDACYeBjFXJ3w3AAAKACOGvcwA';
$endpoint = 'https://piston-face-api.cognitiveservices.azure.com/face/v1.0/verify';

$faceId1 = $_POST['faceId1'] ?? null;
$faceId2 = $_POST['faceId2'] ?? null;

if (!$faceId1 || !$faceId2) {
  echo json_encode(['error' => 'Missing one or both face IDs']);
  exit;
}

$body = json_encode([
  'faceId1' => $faceId1,
  'faceId2' => $faceId2
]);

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Ocp-Apim-Subscription-Key: ' . $subscriptionKey,
  'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200) {
  echo json_encode([
    'status' => 'success',
    'isIdentical' => $data['isIdentical'],
    'confidence' => $data['confidence']
  ]);
} else {
  echo json_encode([
    'status' => 'error',
    'response' => $data,
    'http_code' => $httpCode
  ]);
}
