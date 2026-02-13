<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('output_buffering', 0);
ini_set('error_log', __DIR__ . '/debug.log');

// 🚀 Debug: Log script start
error_log("🚀 get-resources.php STARTED");

// ✅ Prevent extra whitespace or accidental output
ob_start();
echo ""; 

// ✅ Ensure database connection exists
require_once dirname(__DIR__) . '/flight-booking/db_connect.php';
global $conn;

if (!$conn) {
    error_log("❌ Database connection failed");
    ob_end_clean();
    die(json_encode(["error" => "Database connection failed"]));
}

$resources = [];
$groupedResources = [];

// ✅ Fetch Aircraft Resources (Using `home_location`)
$aircraftQuery = "SELECT tail_number, tail_number AS title, home_location FROM wp_aircraft"; 
$aircraftResult = mysqli_query($conn, $aircraftQuery);

if (!$aircraftResult) {
    error_log("❌ SQL Error (Aircraft): " . mysqli_error($conn));
} else {
    while ($row = mysqli_fetch_assoc($aircraftResult)) {
        $location = $row['home_location'] ?: "Other Locations"; 

        if (!isset($groupedResources[$location])) {
            $groupedResources[$location] = [
                "id" => "group_$location",
                "title" => "📍 $location",
                "children" => []
            ];
        }

        $groupedResources[$location]['children'][] = [
            "id" => $row['tail_number'],
            "title" => "✈ " . $row['tail_number'],
            "checkbox" => true // ✅ Add checkbox support
        ];
        error_log("📌 Added Aircraft: " . $row['tail_number']);
    }
}

// ✅ Fetch CFI Resources (Using `home_airport`)
$cfiQuery = "SELECT first_name, last_name, home_airport FROM wp_cfis WHERE status = 'Active'"; 
$cfiResult = mysqli_query($conn, $cfiQuery);

if (!$cfiResult) {
    error_log("❌ SQL Error (CFIs): " . mysqli_error($conn));
} else {
    while ($row = mysqli_fetch_assoc($cfiResult)) {
        $location = $row['home_airport'] ?: "Other Locations"; 
        $cfiId = $row['first_name'] . " " . $row['last_name'] . " (CFI)";

        if (!isset($groupedResources[$location])) {
            $groupedResources[$location] = [
                "id" => "group_$location",
                "title" => "📍 $location",
                "children" => []
            ];
        }

        array_unshift($groupedResources[$location]['children'], [
            "id" => $cfiId,
            "title" => "👨‍✈️ " . $cfiId,
            "checkbox" => true // ✅ Add checkbox support
        ]);
        error_log("📌 Added CFI: " . $cfiId);
    }
}

// ✅ Convert groups into a single resource array
$resources = array_values($groupedResources);
echo json_encode($resources);


// ✅ Ensure clean output
ob_end_clean();
error_log("✅ Total Resources Retrieved: " . count($resources));
echo json_encode($resources);

// ✅ Log if no resources found
if (empty($resources)) {
    error_log("⚠️ No resources found in database");
}
?>

