<?php
require 'db_connect.php'; // Ensure correct database connection

header('Content-Type: application/json');

// ✅ Debugging log to check if home_airport is received
error_log("Fetching CFIs for Airport: " . ($_GET['home_airport'] ?? 'MISSING'));

if (!isset($_GET['home_airport']) || empty($_GET['home_airport'])) {
    echo json_encode(["error" => "Missing or invalid home_airport parameter"]);
    exit;
}

$home_airport = $_GET['home_airport'];

try {
    // ✅ Fetch all CFIs but prioritize filtering by `home_airport`
    $stmt = $pdo->prepare("SELECT cfi_id, first_name, last_name, home_airport FROM wp_cfis WHERE home_airport = :home_airport ORDER BY last_name ASC");
    $stmt->bindParam(':home_airport', $home_airport, PDO::PARAM_STR);
    $stmt->execute();
    $cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$cfis) {
        echo json_encode(["error" => "No CFIs found for this airport."]);
        exit;
    }

    // ✅ Ensure full_name is included in the response
    foreach ($cfis as &$cfi) {
        $cfi['full_name'] = trim($cfi['first_name'] . " " . $cfi['last_name']);
    }

    echo json_encode($cfis);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>






