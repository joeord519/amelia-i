<?php
header('Content-Type: application/json');

// === CONFIG ===
$apiKey = 'HiHRwrMyoP0UYhHWaUwgfq.1oZFU41wX79PCx7wGP6gdK29OAE6aJzYREeOqu9X';
date_default_timezone_set('America/Chicago');

// === 1. INPUT VALIDATION ===
$tail = $_GET['tail'] ?? null;
if (!$tail) {
  echo json_encode(['status' => 'error', 'error' => 'Missing tail number']);
  exit;
}

// === 2. MAP TAIL TO LOCK ID ===
$lockMap = [
  'N2723B' => 'IGP119b04410'
];

if (!isset($lockMap[$tail])) {
  echo json_encode(['status' => 'error', 'error' => 'Unknown tail number']);
  exit;
}

$lockId = $lockMap[$tail];

// === 3. SET START + END TIME (Rounded to the hour) ===
$now = new DateTime();
$now->modify('+1 hour')->setTime($now->format('H'), 0, 0); // round to next top of hour
$end = clone $now;
$end->modify('+1 hour'); // valid for 1 hour

$startFormatted = $now->format('Y-m-d\TH:i:00P');
$endFormatted   = $end->format('Y-m-d\TH:i:00P');

// === 4. BUILD PAYLOAD ===
$payload = [
  "variance" => 1, // Can be 1–3 for hourly
  "startDate" => $startFormatted,
  "endDate" => $endFormatted
];

// === 5. cURL POST to raw algoPIN endpoint ===
$url = "https://api.igloodeveloper.co/locks/$lockId/pin/hourly";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "x-api-key: $apiKey",
  "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TEMP ONLY for testing

$response = curl_exec($ch);

if (curl_errno($ch)) {
  echo json_encode([
    "status" => "curl_error",
    "error" => curl_error($ch)
  ]);
  curl_close($ch);
  exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

// === 6. Return Result ===
if ($httpCode === 200 && isset($data['pin'])) {
  echo json_encode([
    "status" => "success",
    "pin_code" => $data['pin'],
    "start_time" => $startFormatted,
    "end_time" => $endFormatted
  ]);
} else {
  echo json_encode([
    "status" => "error",
    "message" => "PIN generation failed",
    "http_code" => $httpCode,
    "raw_response" => $response
  ]);
}
