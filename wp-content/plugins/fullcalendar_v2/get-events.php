<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('output_buffering', 0);
ini_set('error_log', __DIR__ . '/debug.log');

// 🚀 Debug: Log script start
error_log("🚀 get-events.php STARTED");

// ✅ Prevent extra whitespace or accidental output
ob_start();
echo "";

// ✅ Ensure database connection exists
require_once dirname(__DIR__) . '/flight-booking/db_connect.php';
global $conn;

if (!$conn) {
    error_log("❌ Database connection failed");
    ob_end_clean();
    die(json_encode(["error" => "Database connection failed"]));
}

// ✅ Check if `start` and `end` exist and are valid
if (!isset($_GET['start']) || !isset($_GET['end'])) {
    error_log("❌ Missing start or end parameters");
    ob_end_clean();
    die(json_encode(["error" => "Missing start or end parameters"]));
}

$start = date('Y-m-d H:i:s', strtotime($_GET['start']));
$end = date('Y-m-d H:i:s', strtotime($_GET['end']));

error_log("✅ Using Start: $start | End: $end");

// ✅ Fetch Events
$query = "SELECT fs.id, fs.tail_number, fs.cfi_id, fs.start_time, fs.end_time, fs.flight_type,
                 c.first_name AS cfi_first, c.last_name AS cfi_last,
                 s.first_name AS student_first, s.last_name AS student_last, s.phone AS student_phone
          FROM wp_flight_schedule fs
          LEFT JOIN wp_cfis c ON fs.cfi_id = c.cfi_id
          LEFT JOIN wp_students s ON fs.student_id = s.student_id
          WHERE fs.status = 'Scheduled'
          AND fs.start_time >= ? AND fs.end_time <= ?";

error_log("✅ Running query: " . $query);

$stmt = $conn->prepare($query);
if (!$stmt) {
    error_log("❌ SQL Prepare Error: " . $conn->error);
    ob_end_clean();
    die(json_encode(["error" => "SQL Prepare Error: " . $conn->error]));
}

$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['start_time']) || empty($row['end_time'])) {
        error_log("⚠️ Skipping Event ID: " . $row['id'] . " - Missing start/end time");
        continue;
    }

    // ✅ Assign aircraft and CFI details properly
    $aircraft = !empty($row['tail_number']) ? $row['tail_number'] : "N/A";
    $cfi = (!empty($row['cfi_first']) && !empty($row['cfi_last'])) ? "{$row['cfi_first']} {$row['cfi_last']} (CFI)" : "N/A";

    file_put_contents('debug_calendar.txt', "🔍 CFI Data for Event ID {$row['id']}: " . print_r([$row['cfi_first'], $row['cfi_last']], true) . "\n", FILE_APPEND);
    
    // ✅ Build resource IDs array
    $resourceIds = [$row['tail_number']]; // Assign aircraft first

// ✅ If the flight has a CFI, assign them as a resource
if (!empty($row['cfi_first']) && !empty($row['cfi_last'])) {
    $cfiResourceId = "{$row['cfi_first']} {$row['cfi_last']} (CFI)";
    $resourceIds[] = $cfiResourceId;
}

file_put_contents('debug_calendar.txt', "🔍 Assigned Resources for Event ID {$row['id']}: " . print_r($resourceIds, true) . "\n", FILE_APPEND);
file_put_contents('debug_calendar.txt', "🔍 Flight Type for Event ID {$row['id']}: " . $row['flight_type'] . "\n", FILE_APPEND);

    $events[] = [
        'id' => $row['id'],
        'title' => "✈ " . $row['tail_number'] . " - " . (!empty($row['cfi_first']) ? $row['cfi_first'] : "Solo Flight"),
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'resourceIds' => $resourceIds,
        'extendedProps' => [
            'aircraft' => $aircraft,
            'cfi' => $cfi,
            'student' => $row['student_first'] . " " . $row['student_last'],
            'phone' => $row['student_phone'],
            'flight_type' => $row['flight_type']
        ]
    ];
}

// ✅ Ensure clean output
ob_end_clean();
error_log("✅ Total Events Retrieved: " . count($events));
echo json_encode($events);

// ✅ Log if no events found
if (empty($events)) {
    error_log("⚠️ No events found in database for this time range");
}
?>
