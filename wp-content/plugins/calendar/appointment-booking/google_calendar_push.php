<?php
function getCfiGoogleBusyTimes($conn, $cfi_id, $date) {
  $stmt = $conn->prepare("SELECT google_refresh_token, google_calendar_id FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$cfi_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row || empty($row['google_refresh_token'])) {
    error_log("❌ No refresh token found for CFI $cfi_id");
    return [];
  }

  $refresh_token = $row['google_refresh_token'];
  $calendar_id = $row['google_calendar_id'] ?: 'primary';

  $client_id = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
  $client_secret = "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";

  // Exchange refresh token for access token via cURL
  $token_url = "https://oauth2.googleapis.com/token";

  $ch = curl_init($token_url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $refresh_token,
    'grant_type' => 'refresh_token'
  ]));

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($http_code !== 200) {
    error_log("❌ Failed to refresh token for CFI $cfi_id: HTTP $http_code - $response");
    return [];
  }

  $token_data = json_decode($response, true);
  $access_token = $token_data['access_token'] ?? null;

  if (!$access_token) {
    error_log("❌ No access token returned from Google for CFI $cfi_id");
    return [];
  }

  // Now request busy times from their calendar
  $start = $date . "T00:00:00-06:00";
  $end   = $date . "T23:59:59-06:00";

  $busy_payload = json_encode([
    "timeMin" => $start,
    "timeMax" => $end,
    "items" => [["id" => $calendar_id]]
  ]);

  $ch = curl_init("https://www.googleapis.com/calendar/v3/freeBusy");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $busy_payload);

  $busy_response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($status !== 200) {
    error_log("❌ Failed to fetch busy times for CFI $cfi_id: HTTP $status - $busy_response");
    return [];
  }

  $busy_data = json_decode($busy_response, true);
  $busy_times = $busy_data['calendars'][$calendar_id]['busy'] ?? [];

  $slots = [];
  foreach ($busy_times as $b) {
    $slots[] = [
      'start' => date("Y-m-d H:i:s", strtotime($b['start'])),
      'end' => date("Y-m-d H:i:s", strtotime($b['end']))
    ];
  }

  return $slots;
}
?>