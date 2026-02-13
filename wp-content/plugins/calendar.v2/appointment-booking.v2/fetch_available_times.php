<?php
require_once(__DIR__ . '/../db_connect.php');
$conn = getDB();

require_once("google_calendar_push.php");
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

// Setup log
$debugLog = __DIR__ . '/debug.log';
file_put_contents($debugLog, "---- New Request: " . date('Y-m-d H:i:s') . " ----\n", FILE_APPEND);

$aircraftId = $_GET['aircraft'] ?? '';
$cfiId = $_GET['cfi'] ?? '';
$date = $_GET['date'] ?? '';
$flightTypeId = $_GET['flight_type_id'] ?? '';

file_put_contents($debugLog, "GET Parameters: " . json_encode($_GET) . "\n", FILE_APPEND);

if (!$aircraftId || !$date) {
  file_put_contents($debugLog, "Missing aircraftId or date. Returning empty array.\n", FILE_APPEND);
  echo json_encode([]);
  exit;
}

try {
  // ✅ 1. Determine appointment duration (in seconds)
  $duration = 120; // default 120 mins
  if ($flightTypeId) {
    $stmt = $conn->prepare("SELECT duration_minutes FROM wp_flight_types WHERE id = ?");
    $stmt->execute([$flightTypeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['duration_minutes']) {
      $duration = intval($row['duration_minutes']);
    }
  }
  $blockSeconds = $duration * 60;

  $startOfDay = "$date 06:00:00";
  $endOfDay = "$date 22:30:00";

  // ✅ 2. Pull Google Calendar busy times
  $googleBusy = [];
  if (!empty($cfiId)) {
    file_put_contents($debugLog, "Fetching Google busy times for CFI $cfiId\n", FILE_APPEND);
    $googleBusy = getCfiGoogleBusyTimes($conn, $cfiId, $date);
    file_put_contents($debugLog, "Google Busy Times: " . json_encode($googleBusy) . "\n", FILE_APPEND);
  }

  // ✅ 3. Get booked flights for aircraft or CFI
  $query = "
    SELECT start_time, end_time, flight_type 
    FROM wp_flight_schedule 
    WHERE DATE(start_time) = ? 
      AND (
        (tail_number = ? AND (flight_type IS NULL OR flight_type != 'CFI-Event'))
        " . ($cfiId ? "OR cfi_id = ?" : "") . "
      )
  ";

  $params = [$date, $aircraftId];
  if ($cfiId) $params[] = $cfiId;

  file_put_contents($debugLog, "SQL Query: $query\n", FILE_APPEND);
  file_put_contents($debugLog, "Query Params: " . json_encode($params) . "\n", FILE_APPEND);

  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  $booked = $stmt->fetchAll(PDO::FETCH_ASSOC);
  file_put_contents($debugLog, "Booked Times: " . json_encode($booked) . "\n", FILE_APPEND);

  foreach ($booked as $r) {
    file_put_contents($debugLog, "⛔ Blocked by: {$r['flight_type']} | {$r['start_time']} - {$r['end_time']}\n", FILE_APPEND);
  }

  // ✅ 4. Generate available times in 30-min increments
  $available = [];
  $start = strtotime($startOfDay);
  $end = strtotime($endOfDay);

  for ($slot = $start; $slot <= $end; $slot += 1800) {
    $slotStart = date('Y-m-d H:i:s', $slot);
    $slotEnd = date('Y-m-d H:i:s', $slot + $blockSeconds);
    $conflict = false;

    foreach ($booked as $b) {
      if (($slotStart < $b['end_time']) && ($slotEnd > $b['start_time'])) {
        $conflict = true;
        break;
      }
    }

    foreach ($googleBusy as $g) {
      if (strtotime($slotStart) < $g['end'] && strtotime($slotEnd) > $g['start']) {
        $conflict = true;
        break;
      }
    }

    if (!$conflict) {
      $available[] = date('H:i', $slot);
    }
  }

  file_put_contents($debugLog, "Available Slots: " . json_encode($available) . "\n", FILE_APPEND);
  echo json_encode($available);
} catch (Exception $e) {
  $err = "ERROR: " . $e->getMessage();
  file_put_contents($debugLog, $err . "\n", FILE_APPEND);
  echo json_encode(["error" => $e->getMessage()]);
}