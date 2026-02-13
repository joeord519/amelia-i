<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

// Grab posted JSON
$data = json_decode(file_get_contents('php://input'), true);
$cfi_id = intval($data['cfi_id'] ?? 0);
$email = trim($data['email'] ?? '');
$name = trim($data['name'] ?? '');

if (!$cfi_id || !$email || !$name) {
  echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
  exit;
}

// Create reconnect URL
$reconnectUrl = "https://amelia-i.com/wp-content/plugins/calendar/appointment-booking/cfi-connect.html?cfi_id=$cfi_id";

// Compose email
$subject = "Reconnect Your Calendar – Action Required";
$htmlBody = "
  <p>Hi $name,</p>
  <p>We noticed your Google Calendar connection has expired or become invalid. To continue receiving your scheduled flights, please reconnect your calendar now:</p>
  <p><a href=\"$reconnectUrl\" style=\"background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;\">Reconnect Calendar</a></p>
  <p>If the button above doesn't work, copy and paste this link into your browser:</p>
  <p><code>$reconnectUrl</code></p>
  <p>Thanks!<br>The Piston Aviation Team</p>
";

// Mailgun send function
function sendViaMailgunAPI($to, $name, $subject, $htmlBody) {
    $apiKey = 'api:71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';
    $domain = 'mg.amelia-i.com';
    $url = "https://api.mailgun.net/v3/$domain/messages";

    $postData = [
        'from'    => 'Piston Aviation <postmaster@mg.amelia-i.com>',
        'to'      => "$name <$to>",
        'subject' => $subject,
        'html'    => $htmlBody
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200;
}

// Send the email
$success = sendViaMailgunAPI($email, $name, $subject, $htmlBody);
if ($success) {
  echo json_encode(['success' => true, 'message' => "Reconnect email sent to $name."]);
} else {
  echo json_encode(['success' => false, 'message' => "Failed to send email to $name."]);
}
?>
