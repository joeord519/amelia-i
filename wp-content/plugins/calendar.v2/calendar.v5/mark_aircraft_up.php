<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $tail = strtoupper(trim($input['tail_number'] ?? ''));
    $endedAt = $input['ended_at'] ?? date('Y-m-d H:i:s');

    if (!$tail) {
        throw new Exception('tail_number is required.');
    }

    $db = getDB();
    $db->beginTransaction();

    $updateDowntime = $db->prepare(
        "UPDATE wp_aircraft_downtime
         SET end_at = :ended_at
         WHERE tail_number = :tail
           AND end_at IS NULL
         ORDER BY start_at DESC
         LIMIT 1"
    );
    $updateDowntime->execute([
        ':ended_at' => $endedAt,
        ':tail' => $tail,
    ]);

    $updateAircraft = $db->prepare('UPDATE wp_aircraft SET status = ? WHERE tail_number = ?');
    $updateAircraft->execute(['Available', $tail]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'updated_rows' => $updateDowntime->rowCount(),
        'tail_number' => $tail,
        'status' => 'Available',
    ]);
} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
