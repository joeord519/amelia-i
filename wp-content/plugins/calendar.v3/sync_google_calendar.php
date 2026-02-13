<?php
require_once(__DIR__ . '/db_connect.php');

function getCalendarSummary($flight) {
  if (
    isset($flight['event_title']) &&
    (isset($flight['flight_type']) && stripos($flight['flight_type'], 'Company Event') !== false)
  ) {
    return 'Company Event - ' . $flight['event_title'];
  }

  $flightType = $flight['flight_type_name'] ?? $flight['flight_type'] ?? 'Flight';
  $studentName = '';

  if (isset($flight['flight_type']) && stripos($flight['flight_type'], 'Discovery') !== false) {
    $studentName = $flight['future_student_name'] ?? '';
  } else {
    $studentName = trim(($flight['student_first'] ?? '') . ' ' . ($flight['student_last'] ?? ''));
  }

  $studentPhone = $flight['student_phone'] ?? '';
  $parts = array_filter([$flightType, $studentName, $studentPhone]);
  return implode(' - ', $parts);
}

function createGoogleCalendarEvent(&$flight) {
  if (empty($flight['cfi_id'])) return;

  $db = getDB();
  $stmt = $db->prepare("SELECT google_calendar_id, google_access_token FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$flight['cfi_id']]);
  $cfi = $stmt->fetch();

  if (!$cfi || empty($cfi['google_calendar_id']) || empty($cfi['google_access_token'])) return;

  $tz = new DateTimeZone('America/Chicago');
  $start = (new DateTime($flight['start_time'], $tz))->format('c');
  $end = (new DateTime($flight['end_time'], $tz))->format('c');
  $summary = getCalendarSummary($flight);

  $payload = [
    'summary' => $summary,
    'start' => ['dateTime' => $start, 'timeZone' => 'America/Chicago'],
    'end' => ['dateTime' => $end, 'timeZone' => 'America/Chicago']
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $cfi['google_access_token'],
    'Content-Type: application/json'
  ]);
  $response = curl_exec($ch);
  $data = json_decode($response, true);
  curl_close($ch);

  if (!empty($data['id'])) {
    $stmt = $db->prepare("UPDATE wp_flight_schedule SET google_event_id = ? WHERE id = ?");
    $stmt->execute([$data['id'], $flight['id']]);
    $flight['google_event_id'] = $data['id'];
  }
}

function syncGoogleCalendarUpdate($flight) {
  if (empty($flight['cfi_id']) || empty($flight['google_event_id'])) return;

  $db = getDB();
  $stmt = $db->prepare("SELECT google_calendar_id, google_access_token FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$flight['cfi_id']]);
  $cfi = $stmt->fetch();

  if (!$cfi || empty($cfi['google_calendar_id']) || empty($cfi['google_access_token'])) return;

  $tz = new DateTimeZone('America/Chicago');
  $start = (new DateTime($flight['start_time'], $tz))->format('c');
  $end = (new DateTime($flight['end_time'], $tz))->format('c');
  $summary = getCalendarSummary($flight);

  $payload = [
    'summary' => $summary,
    'start' => ['dateTime' => $start, 'timeZone' => 'America/Chicago'],
    'end' => ['dateTime' => $end, 'timeZone' => 'America/Chicago']
  ];

  $url = "https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events/{$flight['google_event_id']}";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $cfi['google_access_token'],
    'Content-Type: application/json'
  ]);
  curl_exec($ch);
  curl_close($ch);
}

function deleteGoogleCalendarEvent($flight) {
  if (empty($flight['google_event_id']) || empty($flight['cfi_id'])) return;

  $db = getDB();
  $stmt = $db->prepare("SELECT google_calendar_id, google_access_token FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$flight['cfi_id']]);
  $cfi = $stmt->fetch();

  if (!$cfi || empty($cfi['google_calendar_id']) || empty($cfi['google_access_token'])) return;

  $url = "https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events/{$flight['google_event_id']}";

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$cfi['google_access_token']}",
  ]);
  curl_exec($ch);
  curl_close($ch);
}
