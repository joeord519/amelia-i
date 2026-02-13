<?php
require_once __DIR__ . '/db_connect.php';
$conn = getDB();

$client_id     = '179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com';
$client_secret = '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';

$stmt = $conn->query("SELECT cfi_id, first_name, last_name, google_refresh_token FROM wp_cfis WHERE google_refresh_token IS NOT NULL");
$cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logFile = __DIR__ . '/refresh_token_log.txt';
file_put_contents($logFile, "📆 TOKEN REFRESH RUN: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

foreach ($cfis as $cfi) {
  $ch = curl_init("https://oauth2.googleapis.com/token");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $cfi['google_refresh_token'],
    'grant_type' => 'refresh_token'
  ]));

  $response = curl_exec($ch);
  $data = json_decode($response, true);
  curl_close($ch);

  if (isset($data['access_token'])) {
    $access_token = $data['access_token'];
    $expires_in   = intval($data['expires_in'] ?? 3600);
    $expiry       = time() + $expires_in;

    $update = $conn->prepare("UPDATE wp_cfis SET google_access_token = ?, google_token_expires = ? WHERE cfi_id = ?");
    $update->execute([$access_token, $expiry, $cfi['cfi_id']]);

    file_put_contents($logFile, "✅ Refreshed for {$cfi['first_name']} {$cfi['last_name']} (ID {$cfi['cfi_id']})\n", FILE_APPEND);
  } else {
    file_put_contents($logFile, "❌ Failed refresh for {$cfi['first_name']} {$cfi['last_name']} (ID {$cfi['cfi_id']})\n", FILE_APPEND);
  }
}

file_put_contents($logFile, "🔚 Finished refresh cycle.\n\n", FILE_APPEND);
