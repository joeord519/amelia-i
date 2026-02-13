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

// ✅ Get scheduled events for student view
try {
  $stmt = $conn->prepare("
    SELECT 
      s.flight_type,
      s.tail_number,
      s.start_time,
      s.end_time,
      s.cfi_id,
      s.student_id,
      s.home_airport,
      s.future_student_name,
      s.future_student_phone,
      c.first_name AS cfi_first,
      c.last_name  AS cfi_last,
      c.phone      AS cfi_phone,
      st.first_name AS student_first,
      st.last_name  AS student_last,
      st.phone      AS student_phone,
      a.label_color
    FROM wp_flight_schedule s
    LEFT JOIN wp_aircraft a ON s.tail_number = a.tail_number
    LEFT JOIN wp_cfis c     ON s.cfi_id      = c.cfi_id
    LEFT JOIN wp_students st ON s.student_id = st.student_id
    WHERE 
      -- Show flights roughly +/- 30 days around today
      s.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      AND s.start_time <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    ORDER BY s.start_time ASC
  ");

  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $counter = 1;

  foreach ($rows as $row) {
    $tail = $row["tail_number"];
    if ($tail && !isset($aircraft_colors[$tail])) {
      $aircraft_colors[$tail] = randomColor();
    }

    // Normalize CFI events
    if (strtolower($row["flight_type"]) === "cfi-event") {
      $row["flight_type"] = "CFI Unavailable";
      $row["label_color"] = "#9ca3af";
    }

    $studentName = trim(($row["student_first"] ?? '') . ' ' . ($row["student_last"] ?? ''));
    $futureName  = $row["future_student_name"] ?? '';

    $baseEvent = [
      "tail_number" => $tail,
      "flight_type" => $row["flight_type"],
      "start"       => $row["start_time"],
      "end"         => $row["end_time"],
      "airport_code"=> $row["home_airport"] ?? '',
      "cfi_id"      => $row["cfi_id"],
      "cfi_name"    => trim(($row["cfi_first"] ?? '') . ' ' . ($row["cfi_last"] ?? '')),
      "cfi_phone"   => $row["cfi_phone"] ?? '',
      "student_name"=> $studentName,
      "student_phone"=> $row["student_phone"] ?? '',
      "color"       => $row["label_color"] ?: ($tail ? $aircraft_colors[$tail] : '#2563eb'),
      "future_student_name"  => $futureName,
      "future_student_phone" => $row["future_student_phone"] ?? '',
      "gcal_title"           => ''  // placeholder; avoid undefined index
    ];

    $baseId = "evt-" . $counter++;

    // 🔁 Dual display: CFI row + Aircraft row
    if (!empty($row["cfi_id"])) {
      $events[] = array_merge($baseEvent, [
        "id"             => $baseId . "-CFI",
        "resourceId"     => $row["cfi_id"],
        "type"           => "CFI",
        "paired_event_id"=> $baseId . "-AC",
        "title"          => $row["flight_type"] . " → " . ($baseEvent["student_name"] ?: $baseEvent["future_student_name"])
      ]);
    }

    if (!empty($tail)) {
      $events[] = array_merge($baseEvent, [
        "id"             => $baseId . "-AC",
        "resourceId"     => $tail,
        "type"           => "Aircraft",
        "paired_event_id"=> $baseId . "-CFI",
        "title"          => "Aircraft: {$tail}"
      ]);
    }
  }

} catch (Exception $e) {
  echo json_encode(["error" => "Event query failed: " . $e->getMessage()]);
  exit;
}

// ✅ Build CFI and Aircraft resources from events
$cfi_ids = array_unique(array_column($events, 'cfi_id'));
foreach ($cfi_ids as $cfi_id) {
  if ($cfi_id) {
    $matching = array_filter($events, fn($e) => $e["cfi_id"] === $cfi_id);
    $first = reset($matching);
    $resources[] = [
      "id"           => $cfi_id,
      "title"        => $first["cfi_name"] ?: ("CFI #" . $cfi_id),
      "group"        => "CFI",
      "resourceType" => "CFI"
    ];
  }
}

$tail_numbers = array_unique(array_column($events, 'tail_number'));
foreach ($tail_numbers as $tail) {
  if ($tail) {
    $resources[] = [
      "id"           => $tail,
      "title"        => $tail,
      "group"        => "Aircraft",
      "resourceType" => "Aircraft"
    ];
  }
}

// ✅ Final JSON output
echo json_encode([
  "resources" => $resources,
  "events"    => $events
]);



