<?php
require_once(__DIR__ . '/db_connect.php');
header('Content-Type: application/json');

try {
    $db = getDB();
    $rows = $db->query("SELECT tail_number,
                              CONCAT(COALESCE(manufacturer, ''), ' ', COALESCE(model, '')) AS nickname,
                              status,
                              label_color
                       FROM wp_aircraft
                       ORDER BY tail_number ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'aircraft' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
