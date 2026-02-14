<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
    $db = getDB();

    $start = $_GET['start'] ?? date('Y-m-d 00:00:00');
    $end = $_GET['end'] ?? date('Y-m-d 23:59:59');

    $stmt = $db->prepare(
        "SELECT id, tail_number, start_at, end_at, reason, notes
         FROM wp_aircraft_downtime
         WHERE start_at < :end_at
           AND COALESCE(end_at, '9999-12-31 23:59:59') > :start_at
         ORDER BY start_at ASC"
    );
    $stmt->execute([':start_at' => $start, ':end_at' => $end]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => 'dt-' . $row['id'],
            'resourceId' => $row['tail_number'],
            'start' => $row['start_at'],
            'end' => $row['end_at'] ?: null,
            'display' => 'background',
            'backgroundColor' => '#dc2626',
            'title' => 'Downtime',
            'extendedProps' => [
                'isDowntime' => true,
                'reason' => $row['reason'],
                'notes' => $row['notes'],
                'tail_number' => $row['tail_number'],
                'start_at' => $row['start_at'],
                'end_at' => $row['end_at'],
            ],
        ];
    }

    echo json_encode(['success' => true, 'events' => $events]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
