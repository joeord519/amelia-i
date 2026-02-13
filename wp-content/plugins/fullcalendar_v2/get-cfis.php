<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

// 🚀 Debug: Log script start
error_log("🚀 get-cfis.php STARTED");
echo json_encode(["debug" => "Script started"]); // Ensure something is outputted

// ✅ Ensure database connection exists
require_once dirname(__DIR__) . '/flight-booking/db_connect.php';
global $conn;

if (!$conn) {
    error_log("❌ Database connection failed");
    die(json_encode(["error" => "Database connection failed"]));
}

// ✅ Fetch CFIs
$query = "SELECT cfi_id, first_name, last_name FROM wp_cfis WHERE status = 'Active'"; 
$result = mysqli_query($conn, $query);

if (!$result) {
    error_log("❌ SQL Error: " . mysqli_error($conn));
    die(json_encode(["error" => "SQL Query Failed: " . mysqli_error($conn)]));
}

$cfis = [];
while ($row = mysqli_fetch_assoc($result)) {
    $cfis[] = [
        "id" => $row['first_name'] . " " . $row['last_name'] . " (CFI)",
        "title" => $row['first_name'] . " " . $row['last_name'] . " (CFI)"
    ];
}

// 🚀 Debugging Output
error_log("✅ Total CFIs Retrieved: " . count($cfis));
echo json_encode($cfis);

// ✅ If no CFIs found, log it
if (empty($cfis)) {
    error_log("⚠️ No CFIs found in database");
}
?>

