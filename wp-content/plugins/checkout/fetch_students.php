<?php
// ✅ Enable debugging (safe for dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Set JSON response header
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

try {
    $conn = getDB();
    if (!$conn) {
        throw new Exception("❌ DB connection failed.");
    }

    $query = $_POST['query'] ?? '';
    $query = trim($query);

    // ✅ Validate input
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT student_id AS id, CONCAT(first_name, ' ', last_name) AS name, email
        FROM wp_students
        WHERE first_name LIKE ? OR last_name LIKE ?
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
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}



