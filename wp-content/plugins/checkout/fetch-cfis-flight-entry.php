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

    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name
        FROM wp_cfis
        WHERE status = 'Active' AND (first_name LIKE ? OR last_name LIKE ?)
        ORDER BY first_name
        LIMIT 10
    ");
    $search = '%' . $query . '%';
    $stmt->execute([$search, $search]);

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