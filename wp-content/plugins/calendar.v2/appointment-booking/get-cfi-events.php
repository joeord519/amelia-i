<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once('../db_connect.php');
$conn = getDB();

require_once('google_calendar_push.php'); // uses existing token logic

$cfi_id = $_GET['cfi_id'] ?? null;
$start  = $_GET['start']  ?? null;
$end    = $_GET['end']    ?? null;

if (!$cfi_id || !$start || !$end) {
  echo json_encode(["error" => "Missing required parameters"]);
  exit;
}

// 1. Get known scheduled event IDs from wp_flight_schedule
$stmt = $conn->prepare("SELECT google_event_id FROM wp_flight_schedule WHERE cfi_id = ? AND google_event_id IS NOT NULL");
$stmt->execute([$cfi_id]);
$booked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
$booked_map = array_flip($booked_ids);

// 2. Get Google Calendar events for the date range
$stmt = $conn->prepare("SELECT google_calendar_id, google_refresh_token, google_access_token, google_token_expires FROM wp_cfis WHERE cfi_id = ?");
$stmt->execute([$cfi_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['google_refresh_token'])) {
  echo json_encode(["error" => "Missing refresh token for CFI"]);
  exit;
}

$calendar_id   = $row['google_calendar_id'] ?: 'primary';
$refresh_token = $row['google_refresh_token'];
$access_token  = $row['google_access_token'];
$token_expires = intval($row['google_token_expires']);
$now           = time();

$client_id     = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
$client_secret = "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";

// Refresh token if expired
if (!$access_token || $token_expires < $now) {
  $ch = curl_init("https://oauth2.googleapis.com/token");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $refresh_token,
    'grant_type'    => 'refresh_token'
  ]));
  $response = curl_exec($ch);
  $token_data = json_decode($response, true);
  $access_token = $token_data['access_token'] ?? null;
  $expires_in   = intval($token_data['expires_in'] ?? 3600);
  $new_expiry   = $now + $expires_in;

  if ($access_token) {
    $update = $conn->prepare("UPDATE wp_cfis SET google_access_token = ?, google_token_expires = ? WHERE cfi_id = ?");
    $update->execute([$access_token, $new_expiry, $cfi_id]);
  } else {
    echo json_encode(["error" => "Google token refresh failed"]);
    exit;
  }
}

// 3. Pull all events from Google Calendar for the given range
$url = "https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendar_id) . "/events?" . http_build_query([
  'timeMin' => $start . "T00:00:00-06:00",
  'timeMax' => $end . "T23:59:59-06:00",
  'singleEvents' => 'true',
  'orderBy' => 'startTime'
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Authorization: Bearer $access_token"
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status !== 200) {
  echo json_encode(["error" => "Google Calendar API error", "status" => $status, "response" => $response]);
  exit;
}

$data = json_decode($response, true);
$events = $data['items'] ?? [];

$output = [];
foreach ($events as $e) {
  $event_id = $e['id'] ?? '';
  if (!$event_id || isset($booked_map[$event_id])) {
    continue; // Skip known booked flight
  }

  $start = $e['start']['dateTime'] ?? $e['start']['date'] ?? null;
  $end   = $e['end']['dateTime'] ?? $e['end']['date'] ?? null;
  if (!$start || !$end) continue;

  $output[] = [
    'title' => $e['summary'] ?? '(Busy)',
    'start' => $start,
    'end'   => $end,
    'resourceId' => $cfi_id, // Update if you're using CFI names
    'backgroundColor' => 'gray',
    'textColor' => 'white',
    'editable' => false
  ];
}

echo json_encode($output);
