<?php
function sendFlightChangeNotification($flight, $action = 'updated') {
  $student = $flight['future_student_name'] ?? $flight['student_name'] ?? 'Unknown';
  $location = $flight['location'] ?? $flight['home_airport'] ?? 'Unknown';
  $flightType = $flight['flight_type_name'] ?? $flight['flight_type'] ?? 'Flight';

  // Subject based on action
  $subject = match($action) {
    'created' => "🛩️ Flight Created",
    'updated' => "🛩️ Flight Updated",
    'cancelled' => "🛩️ Flight Cancelled",
    default => "🛩️ Flight Notification"
  };

  // Format start/end times
  $newStart = date("M j, Y \\a\\t g:i A", strtotime($flight['start_time']));
  $newEnd = date("g:i A", strtotime($flight['end_time']));
  $oldStart = $flight['original_start_time'] ?? 'Unavailable';
  $oldEnd = $flight['original_end_time'] ?? 'Unavailable';

  $cfiName = trim(($flight['cfi_first'] ?? '') . ' ' . ($flight['cfi_last'] ?? ''));

  // Get change initiator
  $changerName = $flight['modified_by'] ?? 'Unknown';
  $changerPhone = $_COOKIE['user_phone'] ?? '';
  $changerLine = ($action === 'created')
    ? "🆕 Created by: {$changerName}" . ($changerPhone ? " {$changerPhone}" : "")
    : "🔧 Changed by: {$changerName}" . ($changerPhone ? " {$changerPhone}" : "");

  // Build message
  $body = "Flight '{$flightType}' with {$student} has been {$action}.\n\n";

  if ($action !== 'created') {
    $body .= "📅 Previous: {$oldStart} to {$oldEnd}\n";
  }

  $body .= "🕒 New Time: {$newStart} to {$newEnd}\n"
         . "✈️ Aircraft: {$flight['tail_number']}\n"
         . "👨‍🏫 CFI: {$cfiName}\n"
         . "📍 Location: {$location}\n";

  if (!empty($flight['modification_reason'])) {
    $body .= "📝 Reason: {$flight['modification_reason']}\n";
  }

  $body .= "{$changerLine}\n";

  // Add calendar links if created
  if ($action === 'created') {
    $startUtc = date("Ymd\THis\Z", strtotime($flight['start_time'] . " UTC"));
    $endUtc = date("Ymd\THis\Z", strtotime($flight['end_time'] . " UTC"));
    $title = urlencode("Flight: " . $flightType);
    $googleUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$startUtc}/{$endUtc}";

   if ($action === 'created') {
  $startUtc = date("Ymd\THis\Z", strtotime($flight['start_time'] . " UTC"));
  $endUtc = date("Ymd\THis\Z", strtotime($flight['end_time'] . " UTC"));
  $title = urlencode("Flight: " . ($flight['flight_type_name'] ?? 'Booking'));
  $googleUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$startUtc}/{$endUtc}";

  $body .= "\n📅 <a href=\"{$googleUrl}\" target=\"_blank\" style=\"font-weight: bold; color: #3366cc;\">Add to my calendar</a>\n";

}

  }

  // Build recipient list
  $recipients = [];

  if (!empty($flight['future_student_email'])) {
    $recipients[] = $flight['future_student_email'];
  }

  if (!empty($flight['cfi_email'])) {
    $recipients[] = $flight['cfi_email'];
  }

  foreach ($recipients as $email) {
    if ($email) sendViaMailgun($email, $subject, $body);
  }
}

function sendViaMailgun($to, $subject, $text) {
  $apiKey = '71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';
  $domain = 'mg.amelia-i.com';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $apiKey);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
  curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$domain}/messages");
  curl_setopt($ch, CURLOPT_POSTFIELDS, [
  'from' => 'Amelia Calendar <noreply@' . $domain . '>',
  'to' => $to,
  'subject' => $subject,
  'text' => $text,
  'html' => nl2br($text) // or use a separate $htmlBody if needed
]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  // Optional logging
  file_put_contents(__DIR__ . '/mailgun.log', json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'to' => $to,
    'subject' => $subject,
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'mailgun_response' => $response
  ], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}


