<?php
require_once('../db_connect.php');
$conn = getDB();

$client_id = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
$client_secret = "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";
$redirect_uri = "https://amelia-i.com/wp-content/plugins/calendar/appointment-booking/handle_oauth_callback.php";

if (!isset($_GET['code']) || !isset($_GET['state'])) {
  echo "Missing required parameters.";
  exit;
}

$code = $_GET['code'];
$cfi_id = $_GET['state']; // passed as 'state' to link to the correct CFI

// Exchange code for tokens
$token_response = file_get_contents("https://oauth2.googleapis.com/token", false, stream_context_create([
  'http' => [
    'method' => 'POST',
    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    'content' => http_build_query([
      'code' => $code,
      'client_id' => $client_id,
      'client_secret' => $client_secret,
      'redirect_uri' => $redirect_uri,
      'grant_type' => 'authorization_code',
    ])
  ]
]));

$tokens = json_decode($token_response, true);

if (!isset($tokens['access_token'])) {
  echo "Error retrieving access token.";
  exit;
}

$access_token = $tokens['access_token'];
$refresh_token = $tokens['refresh_token'] ?? null;
$expires_in = time() + intval($tokens['expires_in']);

// Optional: get calendar list to allow user to choose (future feature)
$calendarId = "primary";

// Save tokens in the CFI record
try {
  $stmt = $conn->prepare("
    UPDATE wp_cfis
    SET google_access_token = ?, google_refresh_token = ?, google_calendar_id = ?, google_token_expires = ?
    WHERE cfi_id = ?
  ");
  $stmt->execute([
    $access_token,
    $refresh_token,
    $calendarId,
    $expires_in,
    $cfi_id
  ]);

  echo "<h1>✅ Google Calendar Connected!</h1><p>You can now receive flight appointments directly on your calendar.</p>";
} catch (Exception $e) {
  echo "DB error: " . $e->getMessage();
}
