<?php
function getSakariAccessToken() {
  $clientId = 'ee22b1db-ba99-44fe-98e6-7ced160dca40';
  $clientSecret = '7e25e635-1a27-4f79-b508-e78399db83b7';

  $url = 'https://api.sakari.io/oauth2/token'; // corrected endpoint

  $data = json_encode([
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret
  ]);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  file_put_contents(__DIR__ . '/../debug-sms-log.txt', "TOKEN RESPONSE: $response\nHTTP CODE: $httpCode\n", FILE_APPEND);

  $json = json_decode($response, true);
  return $json['access_token'] ?? null;
}

function sendSakariSMS($to, $message) {
  $logFile = __DIR__ . '/../debug-sms-log.txt';
  file_put_contents($logFile, "START SMS\n", FILE_APPEND);

  $token = getSakariAccessToken();
  file_put_contents($logFile, "TOKEN: " . ($token ?? 'NULL') . "\n", FILE_APPEND);

  if (!$token) return false;

  // Format phone number to E.164
  $to = preg_replace('/[^0-9]/', '', $to);
  if (strlen($to) === 10) {
    $to = '+1' . $to;
  }

  $accountId = '686dfa9baa3c37677327720e';
  $url = "https://api.sakari.io/v1/accounts/$accountId/messages";

  $tollFreeNumber = '+18339227859'; 

$payload = json_encode([
  'contacts' => [[ 'mobile' => ['number' => $to] ]],
  'template' => $message,
  'fromPhoneNumber' => $tollFreeNumber
]);


  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
  ]);

  $result = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  file_put_contents($logFile, "SEND RESULT: $result\nHTTP CODE: $httpCode\n", FILE_APPEND);

  curl_close($ch);
  return $httpCode >= 200 && $httpCode < 300;
}


