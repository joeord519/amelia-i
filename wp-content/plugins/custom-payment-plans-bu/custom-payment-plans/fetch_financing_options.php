<?php
require_once('db_connect.php'); // ✅ Ensure correct path

header('Content-Type: application/json');

// ✅ Check if database connection is established
if (!$con) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

// ✅ Run query using MySQLi
$sql = "SELECT * FROM wp_financial_plans";
$result = mysqli_query($con, $sql);

if ($result) {
    if (mysqli_num_rows($result) > 0) {
        $plans = mysqli_fetch_all($result, MYSQLI_ASSOC);
        echo json_encode(["status" => "success", "data" => $plans]);
    } else {
        echo json_encode(["status" => "error", "message" => "No financing plans found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database query failed: " . mysqli_error($con)]);
}

mysqli_close($con); // ✅ Close connection
?>

