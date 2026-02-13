<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT airport_code AS location_code, name AS location_name FROM wp_locations");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($locations);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
?>

