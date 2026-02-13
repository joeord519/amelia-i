<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Ensure No Previous Output Interferes
ob_start();

header('Content-Type: application/json');

require_once('db_connect.php');

// Accept both POST and GET requests
$location_id = trim($_POST['location'] ?? $_GET['location'] ?? null);

if (!$location_id) {
    echo json_encode(['success' => false, 'message' => 'No location provided.']);
    exit;
}

try {
    // Debugging: Log received location ID
    error_log("Received Location ID: " . $location_id);

    // Adjust query to use ENUM format (since home_location is ENUM in your table)
    $query = "SELECT tail_number AS id, model AS name FROM wp_aircraft WHERE home_location = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$location_id]);
    $aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($aircraft) {
       // Clean Any Extra Output Before JSON Encoding
ob_end_clean();
    }

echo json_encode([
    "success" => true,
    "aircraft" => array_values($aircraft_list) // Ensure it's properly formatted
], JSON_PRETTY_PRINT);
exit;

?>





