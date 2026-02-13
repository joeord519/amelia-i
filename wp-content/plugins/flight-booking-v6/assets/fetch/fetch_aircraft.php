<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/db_connect.php";

// ✅ Prevent output before JSON response
if (ob_get_length()) ob_end_clean();

// ✅ Read JSON input safely
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

// ✅ Debugging: Ensure `airport_code` is received
if (!$input || !isset($input['airport_code']) || empty($input['airport_code'])) {
    echo json_encode([
        "success" => false, 
        "message" => "Missing Airport Code Parameter", 
        "received" => $inputJSON
    ]);
    exit();
}

$airport_code = mysqli_real_escape_string($con, $input['airport_code']);

// ✅ Fetch available aircraft for the requested airport
$query = "SELECT tail_number, model FROM wp_aircraft WHERE airport_code = '$airport_code' AND status = 'Available'";

$result = mysqli_query($con, $query);
$aircraft = [];

while ($row = mysqli_fetch_assoc($result)) {
    $aircraft[] = [
        "tail_number" => $row["tail_number"],
        "model" => $row["model"]
    ];
}

// ✅ Ensure JSON is properly returned
if (empty($aircraft)) {
    echo json_encode(["success" => false, "message" => "No Aircraft Available at this airport."]);
} else {
    echo json_encode(["success" => true, "aircraft" => $aircraft]);
}

exit();



