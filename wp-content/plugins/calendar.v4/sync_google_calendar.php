<?php
require_once(__DIR__ . '/db_connect.php');

function getCalendarSummary($flight) {
  if (!empty($flight['flight_type']) && stripos($flight['flight_type'], 'Ground') !== false) {
    return 'Ground Lesson';
  }
  if (!empty($flight['flight_type']) && stripos($flight['flight_type'], 'Discovery') !== false) {
    return 'Discovery Flight - ' . ($flight['future_student_name'] ?? '');
  }
  return 'Flight - ' . ($flight['event_title'] ?? '');
}

function createGoogleCalendarEvent($flight) {
  if (empty($flight['cfi_id'])) return;

  $db = getDB();
  $stmt = $db->prepare("SELECT google_calendar_id, google_access_token FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$flight['cfi_id']]);
  $cfi = $stmt->fetch();

  if (!$cfi) return;

  $tz = new DateTimeZone('America/Chicago');
  $start = (new DateTime($flight['start_time'], $tz))->format('c');
  $end = (new DateTime($flight['end_time'], $tz))->format('c');
  $summary = getCalendarSummary($flight);

  // ✅ New description block for ground lessons
  $description = '';
  if (!empty($flight['flight_type']) && strtolower($flight['flight_type']) === 'ground lesson') {
    $description = "Students Attending:\n";
    if (!empty($flight['students']) && is_array($flight['students'])) {
      foreach ($flight['students'] as $s) {
        $description .= "{$s['name']} - {$s['phone']}\n";
      }
    }
  }

  $payload = [
    'summary' => $summary,
    'description' => $description,
    'start' => ['dateTime' => $start, 'timeZone' => 'America/Chicago'],
    'end' => ['dateTime' => $end, 'timeZone' => 'America/Chicago']
  ];

  $ch = curl_init("https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events");
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
    return $data['id'];
  }

  return null;
}

function updateGoogleCalendarEvent($flight) {
  if (empty($flight['google_event_id']) || empty($flight['cfi_id'])) return;

  $db = getDB();
  $stmt = $db->prepare("SELECT google_calendar_id, google_access_token FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$flight['cfi_id']]);
  $cfi = $stmt->fetch();

  if (!$cfi) return;

  $tz = new DateTimeZone('America/Chicago');
  $start = (new DateTime($flight['start_time'], $tz))->format('c');
  $end = (new DateTime($flight['end_time'], $tz))->format('c');
  $summary = getCalendarSummary($flight);

  $description = '';
  if (!empty($flight['flight_type']) && strtolower($flight['flight_type']) === 'ground lesson') {
  $description = "Students Attending:\\n";
  if (!empty($flight['students']) && is_array($flight['students'])) {
    foreach ($flight['students'] as $s) {
      $description .= "{$s['name']} - {$s['phone']}\n";
    }
  }
}

  $payload = [
    'summary' => $summary,
    'start' => ['dateTime' => $start, 'timeZone' => 'America/Chicago'],
    'end' => ['dateTime' => $end, 'timeZone' => 'America/Chicago']
  ];

  $url = "https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events/{$flight['google_event_id']}";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$cfi['google_access_token']}",
    "Content-Type: application/json"
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

  if (!$cfi) return;

  $url = "https://www.googleapis.com/calendar/v3/calendars/{$cfi['google_calendar_id']}/events/{$flight['google_event_id']}";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$cfi['google_access_token']}"
  ]);
  curl_exec($ch);
  curl_close($ch);
}

