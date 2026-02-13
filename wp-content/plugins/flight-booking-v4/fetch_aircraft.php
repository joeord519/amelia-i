<?php
require 'db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['home_location']) || empty($_GET['home_location'])) {
    echo json_encode(["error" => "Missing or invalid home_location parameter"]);
    exit;
}

$home_location = $_GET['home_location'];
$lesson_type = isset($_GET['lesson_type']) ? strtolower($_GET['lesson_type']) : null;

try {
    error_log("📌 Aircraft Fetch Request | Home Location: " . $home_location . " | Lesson Type: " . $lesson_type);

    if ($lesson_type === 'simulator_lesson') {
        $query = "SELECT tail_number, model, aircraft_type FROM wp_aircraft WHERE home_location = :home_location AND LOWER(aircraft_type) = 'simulator'";
    } else {
        $query = "SELECT tail_number, model, aircraft_type FROM wp_aircraft WHERE home_location = :home_location AND LOWER(aircraft_type) != 'simulator'";
    }

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':home_location', $home_location, PDO::PARAM_STR);
    $stmt->execute();
    $aircraft = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("📌 Aircraft Data Sent: " . json_encode($aircraft));

    if (!$aircraft) {
        echo json_encode(["error" => "No aircraft found for this location"]);
    } else {
        echo json_encode($aircraft);
    }
} catch (PDOException $e) {
    error_log("❌ Database Error: " . $e->getMessage());
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>


