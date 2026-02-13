<?php
require_once __DIR__ . '/db_connect.php'; // ✅ Ensure database connection

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// ✅ Get the selected aircraft and CFI from the request
$aircraft = $_POST['aircraft'] ?? '';
$cfi = $_POST['cfi'] ?? '';

if (empty($aircraft)) {
    echo json_encode(['status' => 'error', 'message' => 'Aircraft selection is required']);
    exit;
}

// ✅ Fetch existing bookings for the selected aircraft
$query = "SELECT DATE(start_time) AS flight_date, start_time, end_time 
          FROM wp_flight_schedule 
          WHERE aircraft_id = ? 
          AND start_time >= NOW()"; // ✅ Only fetch future bookings

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'SQL Error: ' . $conn->error]);
    exit;
}
$stmt->bind_param("s", $aircraft);
$stmt->execute();
$result = $stmt->get_result();

$booked_slots = [];
while ($row = $result->fetch_assoc()) {
    $booked_slots[] = [
        'date' => $row['flight_date'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time']
    ];
}
$stmt->close();

// ✅ Define working hours (Example: 6 AM - 10 PM)
$start_time = strtotime('06:00');
$end_time = strtotime('22:00');
$slot_duration = 30 * 60; // ✅ 30-minute intervals

// ✅ Generate available slots for the next 30 days
$available_slots = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    for ($slot = $start_time; $slot < $end_time; $slot += $slot_duration) {
        $start = date('H:i', $slot);
        $end = date('H:i', $slot + $slot_duration);
        $is_conflict = false;

        // ✅ Check if this slot conflicts with any booked slot
        foreach ($booked_slots as $booking) {
            if ($booking['date'] === $date) {
                $booked_start = date('H:i', strtotime($booking['start_time']));
                $booked_end = date('H:i', strtotime($booking['end_time']));

                if (
                    ($start >= $booked_start && $start < $booked_end) ||
                    ($end > $booked_start && $end <= $booked_end) ||
                    ($start <= $booked_start && $end >= $booked_end) // ✅ Prevent full overlap
                ) {
                    $is_conflict = true;
                    break;
                }
            }
        }

        if (!$is_conflict) {
            $available_slots[] = [
                'date' => $date,
                'start_time' => $start,
                'end_time' => $end
            ];
        }
    }
}

// ✅ Return available slots in JSON format
echo json_encode(['status' => 'success', 'slots' => $available_slots], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>

