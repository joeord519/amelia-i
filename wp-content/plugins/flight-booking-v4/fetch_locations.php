<?php
require 'db_connect.php'; // Ensure correct database connection

header('Content-Type: application/json');

// Prevent any unwanted output before JSON response
ob_clean();
ob_start();

try {
    $stmt = $pdo->prepare("SELECT airport_code, name FROM wp_locations ORDER BY name ASC");
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($locations);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}

// Stop output buffering to prevent extra content
ob_end_flush();
?>
