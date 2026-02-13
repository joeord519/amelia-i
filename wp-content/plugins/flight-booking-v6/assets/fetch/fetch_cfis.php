<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db_connect.php";

// ✅ Read JSON input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

// ✅ Debugging: Ensure `location` is received
if (!$input || !isset($input['location']) || empty($input['location'])) {
    echo json_encode([
        "success" => false, 
        "message" => "Missing Location Parameter", 
        "received" => $input
    ]);
    exit();
}

$location = mysqli_real_escape_string($con, $input['location']);

// ✅ Fetch CFIs for the requested location
$query = "SELECT cfi_id, first_name, last_name 
          FROM wp_cfis 
          WHERE home_airport = '$location' 
          AND status = 'Active'";

$result = mysqli_query($con, $query);
$cfis = [];

while ($row = mysqli_fetch_assoc($result)) {
    $cfis[] = [
        "cfi_id" => $row["cfi_id"],
        "first_name" => $row["first_name"],
        "last_name" => $row["last_name"]
    ];
}

// ✅ Check if CFIs were found
if (empty($cfis)) {
    echo json_encode(["success" => false, "message" => "No CFIs available at this location."]);
} else {
    echo json_encode(["success" => true, "cfis" => $cfis]);
}

exit();



