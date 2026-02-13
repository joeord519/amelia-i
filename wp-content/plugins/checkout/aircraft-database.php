<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$conn = getDB();

// Handle POST to Add Aircraft
// Handle POST to Add Aircraft
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $tail_number = trim($data['tail_number'] ?? '');
    $aircraft_type = trim($data['aircraft_type'] ?? '');
    $manufacturer = trim($data['manufacturer'] ?? '');
    $model = trim($data['model'] ?? '');
    $home_airport = trim($data['home_airport'] ?? '');

    if (!$tail_number || !$aircraft_type || !$manufacturer || !$model || !$home_airport) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO wp_aircraft (tail_number, aircraft_type, manufacturer, model, home_airport, status)
            VALUES (?, ?, ?, ?, ?, 'Available')
        ");
        $stmt->execute([$tail_number, $aircraft_type, $manufacturer, $model, $home_airport]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle GET to Fetch Aircraft
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->query("SELECT tail_number, aircraft_type, home_airport FROM wp_aircraft WHERE status = 'Available' ORDER BY tail_number ASC");
    $aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($aircraft);
    exit;
}
?>
