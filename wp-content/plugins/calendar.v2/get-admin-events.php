<?php
require_once(__DIR__ . '/db_connect.php');
$conn = getDB();

header('Content-Type: application/json');

$allEvents = [];

// Load wp_flight_schedule events
$stmt = $conn->query("SELECT * FROM wp_flight_schedule WHERE status != 'canceled'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $resourceIds = [];

    if (!empty($row['tail_number'])) {
        $resourceIds[] = $row['tail_number'];
    }
    if (!empty($row['cfi_id'])) {
        $resourceIds[] = $row['cfi_id'];
    }

    foreach ($resourceIds as $rid) {
        $allEvents[] = [
            'id' => $row['id'],
            'title' => $row['flight_type'] . ' → ' . $row['student_name'],
            'start' => $row['start'],
            'end' => $row['end'],
            'resourceId' => $rid,
            'backgroundColor' => '#2563eb',
            'textColor' => '#fff',
            'extendedProps' => $row
        ];
    }
}

// Load CFI Google Calendar "unavailable" blocks (CFI-Event)
$stmt = $conn->query("SELECT * FROM wp_flight_schedule WHERE flight_type = 'CFI-Event'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $allEvents[] = [
        'id' => 'gcal-' . $row['id'],
        'title' => 'CFI Unavailable',
        'start' => $row['start'],
        'end' => $row['end'],
        'resourceId' => $row['cfi_id'],
        'backgroundColor' => '#6b7280',
        'textColor' => '#fff',
        'editable' => false,
        'extendedProps' => $row
    ];
}

echo json_encode($allEvents);
?>
