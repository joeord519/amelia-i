<?php
require_once('db_connect.php'); // ✅ Ensure correct path

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

// ✅ Check if database connection is established
if (!$con) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

// ✅ Ensure all required fields are present
if (!isset($data['first_name'], $data['last_name'], $data['email'], $data['phone'], $data['training_program'], $data['down_payment'], $data['payment_plan'], $data['monthly_payment'], $data['prepaid_hours'], $data['throttled_hours'], $data['cosigner'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ Prepare SQL query for MySQLi
$sql = "INSERT INTO wp_leads (first_name, last_name, email, phone, training_program, down_payment, payment_plan, monthly_payment, prepaid_hours, throttled_hours, cosigner, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$stmt = mysqli_prepare($con, $sql);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "sssssdssdss", 
        $data['first_name'], 
        $data['last_name'], 
        $data['email'], 
        $data['phone'], 
        $data['training_program'], 
        $data['down_payment'], 
        $data['payment_plan'], 
        $data['monthly_payment'], 
        $data['prepaid_hours'], 
        $data['throttled_hours'], 
        $data['cosigner']
    );

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "message" => "Lead successfully added"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to insert lead: " . mysqli_error($con)]);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(["status" => "error", "message" => "Database query preparation failed: " . mysqli_error($con)]);
}

mysqli_close($con); // ✅ Close connection
?>

