<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

try {
    $conn = getDB();
    $query = $_POST['query'] ?? '';
    $query = trim($query);

    if (strlen($query) < 1) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT tail_number, tail_number AS id
        FROM wp_aircraft
        WHERE tail_number LIKE ?
        ORDER BY tail_number ASC
        LIMIT 10
    ");
    $search = '%' . $query . '%';
    $stmt->execute([$search]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>