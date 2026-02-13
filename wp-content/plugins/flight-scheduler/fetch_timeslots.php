<?php
error_log("fetch_timeslots.php called with: " . json_encode($_POST));
require_once('db_connect.php');

header('Content-Type: application/json');

// Get POST parameters
$aircraft_id = trim($_POST['aircraft'] ?? null);
$cfi_id = trim($_POST['cfi'] ?? null);
$flight_type = trim($_POST['flightType'] ?? null);

if (!$aircraft_id || !$flight_type) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

try {
    // Get existing bookings for the selected aircraft
    $query = "SELECT start_time, end_time FROM wp_flight_schedule WHERE aircraft_id = ? AND status = 'Scheduled'";
    $params = [$aircraft_id];

    // If dual training, check CFI availability too
    if ($flight_type === "dual_training" || $flight_type === "dual_cross_country") {
        if (!$cfi_id) {
            echo json_encode(['success' => false, 'message' => 'CFI is required for this flight type.']);
            exit;
        }
        $query .= " OR (cfi_id = ? AND status = 'Scheduled')";
        $params[] = $cfi_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $booked_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate time slots from 6 AM to 10 PM in 30-minute increments
    $start_time = new DateTime("06:00");
    $end_time = new DateTime("22:00");
    $interval = new DateInterval("PT30M"); // 30 minutes

    $available_slots = [];
    while ($start_time < $end_time) {
        $slot_start = clone $start_time;
        $slot_end = clone $start_time;
        $slot_end->add($interval);

        // Check if this slot conflicts with any booked slot
        $conflict = false;
        foreach ($booked_slots as $booked) {
            $booked_start = new DateTime($booked['start_time']);
            $booked_end = new DateTime($booked['end_time']);

            if (($slot_start >= $booked_start && $slot_start < $booked_end) ||
                ($slot_end > $booked_start && $slot_end <= $booked_end)) {
                $conflict = true;
                break;
            }
        }

        if (!$conflict) {
            $available_slots[] = [
                'date' => date("Y-m-d"), // Current date (this can be modified for multi-day selection)
                'start_time' => $slot_start->format("H:i"),
                'end_time' => $slot_end->format("H:i")
            ];
        }

        $start_time->add($interval);
    }

   // Clean Output Buffer to Prevent Extra Characters
ob_clean();
header('Content-Type: application/json');

// Ensure $available_slots is an array and encode it properly
echo json_encode(["success" => true, "slots" => array_values($available_slots)], JSON_PRETTY_PRINT);
exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
exit;
?>

