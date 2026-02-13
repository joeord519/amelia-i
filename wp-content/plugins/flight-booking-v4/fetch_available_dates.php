<?php
require 'db_connect.php'; // Ensure proper DB connection

header('Content-Type: application/json');

try {
    $query = "
        SELECT DISTINCT DATE(start_time) AS available_date
        FROM wp_flight_schedule
        WHERE start_time >= NOW()
        AND slot_status = 'Available'
        ORDER BY start_time ASC;
    "; // ✅ Closing quote added here

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($dates);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>

