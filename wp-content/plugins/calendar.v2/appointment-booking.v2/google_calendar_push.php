<?php

function getCfiGoogleBusyTimes($conn, $cfi_id, $date) {
  $debugLog = __DIR__ . '/debug.log';
  file_put_contents($debugLog, "🔍 Running getCfiGoogleBusyTimes for CFI $cfi_id on $date\n", FILE_APPEND);

  $stmt = $conn->prepare("SELECT google_calendar_id, google_refresh_token, google_access_token, google_token_expires FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$cfi_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row || empty($row['google_refresh_token'])) {
    file_put_contents($debugLog, "❌ No refresh token found for CFI $cfi_id\n", FILE_APPEND);
    return [];
  }

  $calendar_id     = $row['google_calendar_id'] ?: 'primary';
  $refresh_token   = $row['google_refresh_token'];
  $access_token    = $row['google_access_token'];
  $token_expires   = intval($row['google_token_expires'] ?? 0);
  $now             = time();

  $client_id       = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
  $client_secret   = "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";

  if (!$access_token || $token_expires < $now) {
    file_put_contents($debugLog, "🔁 Refreshing token for CFI $cfi_id...\n", FILE_APPEND);

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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
      file_put_contents($debugLog, "❌ Failed to refresh token for CFI $cfi_id: HTTP $http_code\nResponse: $response\n", FILE_APPEND);
      return [];
    }

    $token_data = json_decode($response, true);
    $access_token = $token_data['access_token'] ?? null;
    $expires_in   = intval($token_data['expires_in'] ?? 3600);
    $new_expiry   = $now + $expires_in;

    if (!$access_token) {
      file_put_contents($debugLog, "❌ No access token received from Google for CFI $cfi_id\n", FILE_APPEND);
      return [];
    }

    $update = $conn->prepare("UPDATE wp_cfis SET google_access_token = ?, google_token_expires = ? WHERE cfi_id = ?");
    $update->execute([$access_token, $new_expiry, $cfi_id]);
    file_put_contents($debugLog, "✅ Refreshed token stored. Expires at $new_expiry\n", FILE_APPEND);

    // ✅ Run sync after refresh
    syncCfiGoogleEvents($conn, $cfi_id, $access_token, $calendar_id);

} else {
    // ✅ Run sync even if token was still valid
    syncCfiGoogleEvents($conn, $cfi_id, $access_token, $calendar_id);
}

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
    file_put_contents($debugLog, "❌ Google Free/Busy API failed for CFI $cfi_id: HTTP $status\nResponse: $busy_response\n", FILE_APPEND);
    return [];
  }

  $busy_data = json_decode($busy_response, true);
  $busy_times = $busy_data['calendars'][$calendar_id]['busy'] ?? [];

  $slots = [];
  foreach ($busy_times as $b) {
    $slots[] = [
      'start' => strtotime($b['start']),
      'end'   => strtotime($b['end'])
    ];
  }

  file_put_contents($debugLog, "✅ Google busy slots for CFI $cfi_id: " . json_encode($slots) . "\n", FILE_APPEND);
  return $slots;
}


function pushToGoogleCalendar($conn, $flightData) {
  $cfiId = $flightData['cfiId'];
  $stmt = $conn->prepare("SELECT google_calendar_id, google_refresh_token, google_access_token, google_token_expires FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$cfiId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row || empty($row['google_refresh_token'])) {
    error_log("❌ No refresh token for CFI $cfiId");
    return false;
  }

  $calendar_id     = $row['google_calendar_id'] ?: 'primary';
  $refresh_token   = $row['google_refresh_token'];
  $access_token    = $row['google_access_token'];
  $token_expires   = intval($row['google_token_expires'] ?? 0);
  $now             = time();

  $client_id       = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
  $client_secret   = "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";

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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
      error_log("❌ Failed to refresh token for CFI $cfiId");
      return false;
    }

    $token_data = json_decode($response, true);
    $access_token = $token_data['access_token'] ?? null;
    $expires_in   = intval($token_data['expires_in'] ?? 3600);
    $new_expiry   = $now + $expires_in;

    if (!$access_token) {
      error_log("❌ No access token received from Google for CFI $cfiId");
      return false;
    }

    $update = $conn->prepare("UPDATE wp_cfis SET google_access_token = ?, google_token_expires = ? WHERE cfi_id = ?");
    $update->execute([$access_token, $new_expiry, $cfiId]);
  }

  $start = date('Y-m-d\TH:i:s', strtotime($flightData['date'] . ' ' . $flightData['time'] . ':00'));
  $timezone = 'America/Chicago';

  $duration = 120;
  $stmt = $conn->prepare("SELECT default_duration FROM wp_flight_types WHERE id = ?");
  $stmt->execute([$flightData['flightTypeId']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && intval($row['default_duration'])) {
    $duration = intval($row['default_duration']);
  }

  $end = date('Y-m-d\TH:i:s', strtotime("+$duration minutes", strtotime($start)));

  $event = [
    'summary'     => $flightData['flightTypeName'] . ' – ' . $flightData['cfiName'] . ' – ' . $flightData['aircraftId'],
    'description' => "Auto-booked via PistonOps\nStudent: " . $flightData['phone'],
    'start' => [
      'dateTime' => $start,
      'timeZone' => $timezone
    ],
    'end' => [
      'dateTime' => $end,
      'timeZone' => $timezone
    ]
  ];

  $ch = curl_init("https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));

  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($status === 200 || $status === 201) {
    $event = json_decode($response, true);
    return $event['id'] ?? false;
  }

  error_log("❌ Google Calendar push failed for CFI $cfiId: $response");
  return false;
}


function deleteGoogleCalendarEvent($conn, $cfiId, $eventId) {
  $stmt = $conn->prepare("SELECT google_calendar_id, google_refresh_token, google_access_token, google_token_expires FROM wp_cfis WHERE cfi_id = ?");
  $stmt->execute([$cfiId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row || empty($row['google_refresh_token']) || empty($eventId)) {
    file_put_contents(__DIR__ . '/debug.log', "❌ Missing credentials or event ID for cancellation\n", FILE_APPEND);
    return false;
  }

  $calendar_id   = $row['google_calendar_id'] ?: 'primary';
  $refresh_token = $row['google_refresh_token'];
  $access_token  = $row['google_access_token'];
  $token_expires = intval($row['google_token_expires']);
  $now           = time();

  $client_id     = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
  $client_secret = "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";

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

    if (!$access_token) {
      file_put_contents(__DIR__ . '/debug.log', "❌ Refresh failed for deletion\n", FILE_APPEND);
      return false;
    }

    $update = $conn->prepare("UPDATE wp_cfis SET google_access_token = ?, google_token_expires = ? WHERE cfi_id = ?");
    $update->execute([$access_token, $new_expiry, $cfiId]);
  }

  $url = "https://www.googleapis.com/calendar/v3/calendars/$calendar_id/events/$eventId";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
  ]);
  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  file_put_contents(__DIR__ . '/debug.log', "🧹 DELETE event $eventId HTTP $http_code\n", FILE_APPEND);

  return $http_code === 204;
}

function syncCfiGoogleEvents($conn, $cfi_id, $access_token, $calendar_id) {
  $debugLog = __DIR__ . '/debug.log';
  file_put_contents($debugLog, "📣 syncCfiGoogleEvents CALLED for CFI $cfi_id\n", FILE_APPEND);

  $start = date('c');
  $end = date('c', strtotime('+30 days'));

  $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events?timeMin={$start}&timeMax={$end}&singleEvents=true&orderBy=startTime";

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
  ]);
  $response = curl_exec($ch);
  curl_close($ch);

  $events = json_decode($response, true)['items'] ?? [];
  file_put_contents($debugLog, "📅 Fetched " . count($events) . " CFI events for ID $cfi_id\n", FILE_APPEND);

  foreach ($events as $event) {
    $startRaw = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;
    $endRaw   = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;
    $gcal_id  = $event['id'] ?? null;
    $title    = $event['summary'] ?? 'CFI Event'; // <-- ✅ This is what we want to store

    if ($startRaw && $endRaw && $gcal_id) {
      $start = date('Y-m-d H:i:s', strtotime($startRaw));
      $end   = date('Y-m-d H:i:s', strtotime($endRaw));

      // Skip if already exists
      $stmt = $conn->prepare("SELECT COUNT(*) FROM wp_flight_schedule WHERE google_event_id = ?");
      $stmt->execute([$gcal_id]);
      if ($stmt->fetchColumn() > 0) continue;

      // Insert with title
      $insert = $conn->prepare("INSERT INTO wp_flight_schedule 
        (flight_type, cfi_id, start_time, end_time, google_event_id, google_event_title) 
        VALUES ('CFI-Event', ?, ?, ?, ?, ?)");
      $insert->execute([$cfi_id, $start, $end, $gcal_id, $title]);

      file_put_contents($debugLog, "➕ Added CFI-Event: $title from $start to $end\n", FILE_APPEND);
    }
  }
}


