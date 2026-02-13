<?php
require_once('../db_connect.php');
$conn = getDB();

require_once("google_calendar_push.php");
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

$aircraftId = $_GET['aircraft'] ?? '';
$cfiId = $_GET['cfi'] ?? '';
$date = $_GET['date'] ?? '';
$flightTypeId = $_GET['flight_type_id'] ?? '';

if (!$aircraftId || !$date) {
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
    $googleBusy = getCfiGoogleBusyTimes($conn, $cfiId, $date);
  }

  // ✅ 3. Get booked flights for aircraft or CFI
  $query = "
    SELECT start_time, end_time 
    FROM wp_flight_schedule 
    WHERE DATE(start_time) = ? 
      AND (
        tail_number = ?
        " . ($cfiId ? "OR cfi_id = ?" : "") . "
      )
  ";
  $params = [$date, $aircraftId];
  if ($cfiId) $params[] = $cfiId;

  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  $booked = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // ✅ 4. Generate available times in 30-min increments
  $available = [];
  $start = strtotime($startOfDay);
  $end = strtotime($endOfDay);

  for ($slot = $start; $slot <= $end; $slot += 1800) {
    $slotStart = date('Y-m-d H:i:s', $slot);
    $slotEnd = date('Y-m-d H:i:s', $slot + $blockSeconds);

    $conflict = false;

    // ✅ 5. Block if overlaps with booked flights
    foreach ($booked as $b) {
      if (
        ($slotStart < $b['end_time']) &&
        ($slotEnd > $b['start_time'])
      ) {
        $conflict = true;
        break;
      }
    }

    // ✅ 6. Block if overlaps with Google Calendar
    foreach ($googleBusy as $g) {
      if (
        strtotime($slotStart) < $g['end'] &&
        strtotime($slotEnd) > $g['start']
      ) {
        $conflict = true;
        break;
      }
    }

    if (!$conflict) {
      $available[] = date('H:i', $slot);
    }
  }

  file_put_contents($debugLog, "Returned Booked Flights: " . json_encode($booked) . "\n", FILE_APPEND);

  echo json_encode($available);
} catch (Exception $e) {
  echo json_encode(["error" => $e->getMessage()]);
}

