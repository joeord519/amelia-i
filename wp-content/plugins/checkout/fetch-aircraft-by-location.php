<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

try {
    $conn = getDB();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}

$location = $_GET['location'] ?? '';
if (empty($location)) {
    http_response_code(400);
    echo json_encode(['error' => 'Location parameter missing']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT tail_number, model FROM wp_aircraft WHERE home_airport = :location AND status = 'Available'");
    $stmt->execute(['location' => $location]);
    $aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($aircraft);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
?>


