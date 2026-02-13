<?php
require_once "db_connect.php"; // Ensure database connection is available

header("Content-Type: application/json");

// ✅ Check if location is provided in the request
if (!isset($_GET['location']) || empty($_GET['location'])) {
    echo json_encode(["success" => false, "message" => "Missing location parameter."]);
    exit();
}

$location = $_GET['location'];

try {
    $query = "SELECT tail_number, model FROM wp_aircraft WHERE home_airport = :location AND aircraft_type = 'Simulator'";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(":location", $location);
    $stmt->execute();
    $simulators = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$simulators) {
        echo json_encode(["success" => false, "message" => "No simulators found at this location."]);
    } else {
        echo json_encode(["success" => true, "simulators" => $simulators]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
