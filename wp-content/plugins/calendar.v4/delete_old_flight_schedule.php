<?php
require_once(__DIR__ . '/db_connect.php'); // ✅ same directory
$conn = getDB();

try {
    $sql = "DELETE FROM wp_flight_schedule WHERE start < DATE_SUB(NOW(), INTERVAL 18 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    $count = $stmt->rowCount();
    echo "✅ Deleted $count old flight schedule record(s).\n";
} catch (Exception $e) {
    error_log("❌ Flight schedule cleanup error: " . $e->getMessage());
    echo "❌ Error: " . $e->getMessage();
}

