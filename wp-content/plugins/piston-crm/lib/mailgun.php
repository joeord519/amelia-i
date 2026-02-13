<?php
function sendMailgunEmail($to, $subject, $html, $replyTo = 'replies@mail.flypiston.com', $text = null) {
  $apiKey = '71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164'; // ✅ Live key
  $domain = 'mg.amelia-i.com';
  $url = "https://api.mailgun.net/v3/$domain/messages";

  $postData = [
    'from' => 'Joe at Piston Aviation <joe@mail.flypiston.com>',
    'to' => $to,
    'subject' => $subject,
    'html' => $html,
    'text' => $text ?: strip_tags($html),
    'h:Reply-To' => $replyTo
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

  $result = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  $log = "TO: $to\nHTTP: $httpCode\nRESPONSE:\n$result\n";

  if (curl_errno($ch)) {
    $log .= "CURL ERROR: " . curl_error($ch) . "\n";
  }

  $log .= "--------------------------\n";
  file_put_contents(__DIR__ . '/../mailgun-debug.log', $log, FILE_APPEND);
  curl_close($ch);

  return $httpCode === 200;
}

