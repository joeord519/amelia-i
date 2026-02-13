<?php
header("Content-Type: application/json"); // ✅ Ensure JSON response

// ✅ Enable error reporting (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php'; // ✅ Ensure database connection

// ✅ Prepare SQL Query to Fetch Locations
$query = "SELECT airport_code, name FROM wp_locations";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die(json_encode(["success" => false, "message" => "SQL Prepare Failed: " . $conn->error]));
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die(json_encode(["success" => false, "message" => "SQL Execution Failed: " . $stmt->error]));
}

$locationsData = $result->fetch_all(MYSQLI_ASSOC);

// ✅ Debug Log - Check If Data is Fetched
error_log("✅ fetch_locations.php Output: " . json_encode($locationsData));

echo json_encode(["success" => true, "locations" => $locationsData]);
exit;
?>



