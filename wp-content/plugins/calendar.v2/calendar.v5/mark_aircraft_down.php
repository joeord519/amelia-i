<?php
require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/includes/aircraft_availability.php');
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);

    $tail = strtoupper(trim($input['tail_number'] ?? ''));
    $startAt = $input['start_at'] ?? null;
    $endAt = $input['end_at'] ?? null;
    $reason = trim($input['reason'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $status = trim($input['status'] ?? 'Maintenance');
    $createdBy = !empty($input['created_by']) ? (int)$input['created_by'] : null;

    if (!$tail || !$startAt || !$reason) {
        throw new Exception('tail_number, start_at, and reason are required.');
    }

    if (!in_array($status, ['Maintenance', 'Out of Service'], true)) {
        throw new Exception('status must be Maintenance or Out of Service.');
    }

    $db = getDB();
    $db->beginTransaction();

    $insert = $db->prepare(
        'INSERT INTO wp_aircraft_downtime (tail_number, start_at, end_at, reason, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $insert->execute([$tail, $startAt, $endAt ?: null, $reason, $notes ?: null, $createdBy]);

    $updateAircraft = $db->prepare('UPDATE wp_aircraft SET status = ? WHERE tail_number = ?');
    $updateAircraft->execute([$status, $tail]);

    $notify = notify_aircraft_markdown_students($db, $tail, $startAt, $endAt, $reason);

    $db->commit();

    echo json_encode([
        'success' => true,
        'downtime_id' => $db->lastInsertId(),
        'status' => $status,
        'affected_count' => $notify['affected_count'],
        'emails_sent' => $notify['emails_sent'],
        'affected_flights' => $notify['affected_flights'],
    ]);
} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
