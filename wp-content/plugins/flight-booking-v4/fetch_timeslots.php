<?php
require 'db_connect.php';

header('Content-Type: application/json');

// Validate date input
if (!isset($_GET['date']) || empty($_GET['date'])) {
    echo json_encode(["error" => "Missing or invalid date parameter"]);
    exit;
}

$selected_date = $_GET['date'];

try {
    // Ensure selected_date is properly formatted as DATE
    $query = "
        SELECT t.start_time, t.end_time
        FROM (
            SELECT 
                STR_TO_DATE(:selected_date, '%Y-%m-%d') + INTERVAL seq HOUR + INTERVAL minute MINUTE AS start_time, 
                STR_TO_DATE(:selected_date, '%Y-%m-%d') + INTERVAL seq HOUR + INTERVAL minute MINUTE + INTERVAL 2 HOUR AS end_time
            FROM (
                SELECT 6 AS seq UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION 
                SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION 
                SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21
            ) t,
            (SELECT 0 AS minute UNION SELECT 30) m  -- Generates 30-minute intervals
        ) t
        WHERE NOT EXISTS (
            SELECT 1 FROM wp_flight_schedule f
            WHERE DATE(f.start_time) = STR_TO_DATE(:selected_date, '%Y-%m-%d')
            AND f.start_time = t.start_time
        )
        ORDER BY t.start_time ASC;
    ";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':selected_date', $selected_date, PDO::PARAM_STR);
    $stmt->execute();
    $timeslots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($timeslots);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>

