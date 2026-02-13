<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_connect.php';
$conn = getDB();

function randomColor() {
  $colors = ['#f87171','#60a5fa','#34d399','#facc15','#a78bfa','#f472b6','#fb923c','#4ade80','#c084fc','#fcd34d'];
  return $colors[array_rand($colors)];
}

$events = [];
$resources = [];
$aircraft_colors = [];

// ✅ 1. Get scheduled events from wp_flight_schedule
try {
  $stmt = $conn->prepare("SELECT 
    s.flight_type, s.tail_number, s.start_time, s.end_time, s.cfi_id, s.student_id, s.home_airport,
    s.future_student_name, s.future_student_phone,
    c.first_name AS cfi_first, c.last_name AS cfi_last, c.phone AS cfi_phone,
    st.first_name AS student_first, st.last_name AS student_last, st.phone AS student_phone
    FROM wp_flight_schedule s
    LEFT JOIN wp_cfis c ON s.cfi_id = c.cfi_id
    LEFT JOIN wp_students st ON s.student_id = st.student_id
    WHERE s.status = 'Scheduled' 
      AND s.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND s.start_time <= DATE_ADD(NOW(), INTERVAL 30 DAY)");
  $stmt->execute();
  $rows = $stmt->fetchAll();

  foreach ($rows as $row) {
    $tail = $row["tail_number"];
    if (!isset($aircraft_colors[$tail])) {
      $aircraft_colors[$tail] = randomColor();
    }

    $events[] = [
      "tail_number" => $tail,
      "flight_type" => $row["flight_type"],
      "start" => $row["start_time"],
      "end" => $row["end_time"],
      "airport_code" => $row["home_airport"] ?? '',
      "cfi_id" => $row["cfi_id"],
      "cfi_name" => trim(($row["cfi_first"] ?? '') . ' ' . ($row["cfi_last"] ?? '')),
      "cfi_phone" => $row["cfi_phone"] ?? '',
      "student_name" => trim(($row["student_first"] ?? '') . ' ' . ($row["student_last"] ?? '')),
      "student_phone" => $row["student_phone"] ?? '',
      "color" => $aircraft_colors[$tail],
      "future_student_name" => $row["future_student_name"] ?? '',
      "future_student_phone" => $row["future_student_phone"] ?? ''
    ];
  }

} catch (Exception $e) {
  echo json_encode(["error" => "Event query failed: " . $e->getMessage()]);
  exit;
}

// ✅ 2. Pull Google Calendar events for CFIs
try {
  $stmt = $conn->prepare("SELECT cfi_id, first_name, last_name, google_calendar_id, google_access_token 
                          FROM wp_cfis 
                          WHERE google_calendar_id IS NOT NULL AND google_access_token != ''");
  $stmt->execute();
  $cfis = $stmt->fetchAll();

  $now = new DateTime();
  $timeMin = $now->format(DateTime::ATOM);
  $timeMax = $now->modify('+30 days')->format(DateTime::ATOM);

  foreach ($cfis as $cfi) {
    $calendarId = urlencode($cfi['google_calendar_id']);
    $accessToken = $cfi['google_access_token'];
    $cfiName = $cfi['first_name'] . ' ' . $cfi['last_name'];
    $cfiId = $cfi['cfi_id'];

    $url = "https://www.googleapis.com/calendar/v3/calendars/$calendarId/events?timeMin=$timeMin&timeMax=$timeMax&singleEvents=true&orderBy=startTime";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $accessToken",
      "Accept: application/json"
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 && $response) {
      $data = json_decode($response, true);
      if (!empty($data['items'])) {
        foreach ($data['items'] as $item) {
          if (!isset($item['start']['dateTime']) || !isset($item['end']['dateTime'])) continue;

          $events[] = [
            "flight_type" => "GCal Event",
            "start" => $item['start']['dateTime'],
            "end" => $item['end']['dateTime'],
            "cfi_id" => $cfiId,
            "cfi_name" => $cfiName,
            "tail_number" => "",
            "student_name" => $item['summary'] ?? '',
            "student_phone" => '',
            "airport_code" => '',
            "cfi_phone" => '',
            "future_student_name" => '',
            "future_student_phone" => '',
            "color" => "#d1d5db" // light gray for GCal
          ];
        }
      }
    }
  }
} catch (Exception $e) {
  echo json_encode(["error" => "GCal fetch error: " . $e->getMessage()]);
  exit;
}

// ✅ 3. Add CFIs who are in upcoming scheduled events or GCal
$cfi_ids = array_unique(array_column($events, 'cfi_id'));
foreach ($cfi_ids as $cfi_id) {
  if ($cfi_id) {
    $matching = array_filter($events, fn($e) => $e["cfi_id"] === $cfi_id);
    $first = reset($matching);
    $resources[] = [
      "id" => $cfi_id,
      "title" => $first["cfi_name"],
      "group" => "CFI"
    ];
  }
}

// ✅ 4. Add Aircraft used in upcoming events
$tail_numbers = array_unique(array_column($events, 'tail_number'));
foreach ($tail_numbers as $tail) {
  if ($tail) {
    $resources[] = [
      "id" => $tail,
      "title" => $tail,
      "group" => "Aircraft"
    ];
  }
}

// 🔁 Append GCal events for CFIs with tokens
try {
  $cfiStmt = $conn->prepare("SELECT cfi_id, first_name, last_name, google_calendar_id, google_access_token
    FROM wp_cfis 
    WHERE google_calendar_id IS NOT NULL AND google_calendar_id != ''
      AND google_access_token IS NOT NULL AND google_access_token != ''");
  $cfiStmt->execute();
  $cfis = $cfiStmt->fetchAll();

  $now = date('c');
  $maxTime = date('c', strtotime('+30 days'));

  foreach ($cfis as $cfi) {
    $accessToken = $cfi['google_access_token'];
    $calendarId = urlencode($cfi['google_calendar_id']);
    $cfi_id = $cfi['cfi_id'];
    $cfi_name = trim($cfi['first_name'] . ' ' . $cfi['last_name']);

    $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?timeMin={$now}&timeMax={$maxTime}&singleEvents=true&orderBy=startTime";

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $accessToken",
        "Accept: application/json"
      ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
      $data = json_decode($response, true);
      foreach ($data['items'] as $item) {
        if (empty($item['start']['dateTime']) || empty($item['end']['dateTime'])) continue;

        $events[] = [
          "tail_number" => "", // Not relevant for GCal pulls
          "flight_type" => $item['summary'] ?? 'GCal Event',
          "start" => $item['start']['dateTime'],
          "end" => $item['end']['dateTime'],
          "airport_code" => '',
          "cfi_id" => $cfi_id,
          "cfi_name" => $cfi_name,
          "cfi_phone" => '',
          "student_name" => '',
          "student_phone" => '',
          "color" => "#9ca3af" // Light gray for external events
        ];
      }
    }
  }
} catch (Exception $e) {
  error_log("GCal pull failed: " . $e->getMessage());
}

// ✅ 5. Final JSON output
echo json_encode([
  "resources" => $resources,
  "events" => $events
]);


