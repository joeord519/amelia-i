<?php
require_once('db_connect.php');

header('Content-Type: application/json'); // Ensure correct JSON response

// Accept both POST and GET requests
$home_airport = trim($_POST['location'] ?? $_GET['location'] ?? null);

if (!$home_airport) {
    echo json_encode(['success' => false, 'message' => 'No location provided.']);
    exit;
}

try {
    // Debugging: Log received location ID
    error_log("Received Home Airport: " . $home_airport);

    // Fetch CFIs based on home_airport
    $query = "SELECT cfi_id AS id, CONCAT(first_name, ' ', last_name) AS name FROM wp_cfis WHERE home_airport = ? AND status = 'Active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$home_airport]);
    $cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($cfis) {
        echo json_encode(['success' => true, 'cfis' => $cfis]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No CFIs available for this location.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
exit;
?>

