<?php
header('Content-Type: application/json');

// Azure config
$subscriptionKey = 'FoXwTOlGdzh9WfGPUXm6V6HQaYkLwqYJe9CKrWlHs7guFit4KInYJQQJ99BDACYeBjFXJ3w3AAAKACOGvcwA';
$endpoint = 'https://piston-face-api.cognitiveservices.azure.com/face/v1.0/detect';

// Get image URL
$url = $_GET['url'] ?? null;
if (!$url) {
  echo json_encode(['error' => 'Missing image URL']);
  exit;
}

$body = json_encode(['url' => $url]);

$ch = curl_init($endpoint . '?returnFaceId=true');
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

if ($httpCode === 200 && isset($data[0]['faceId'])) {
  echo json_encode([
    'status' => 'success',
    'faceId' => $data[0]['faceId']
  ]);
} else {
  echo json_encode([
    'status' => 'error',
    'response' => $data,
    'http_code' => $httpCode
  ]);
}
