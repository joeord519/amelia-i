<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Prevent any unexpected output
if (ob_get_length()) ob_end_clean();

require_once __DIR__ . "/db_connect.php";

// ✅ Ensure database connection
if (!isset($con)) {
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit();
}

// ✅ Read JSON input
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if (!isset($input['phone']) || !isset($input['securityWord'])) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit();
}

$phone = mysqli_real_escape_string($con, $input['phone']);
$securityWord = mysqli_real_escape_string($con, $input['securityWord']);

// ✅ Fetch student data
$query = "SELECT student_id, CONCAT(first_name, ' ', last_name) AS student_name, 
                 home_airport, current_lesson
          FROM wp_students 
          WHERE phone = '$phone' AND security_word = '$securityWord'";

$result = mysqli_query($con, $query);
$student = mysqli_fetch_assoc($result);

// ✅ Ensure `home_airport` is retrieved
if (!$student) {
    echo json_encode(["success" => false, "message" => "Invalid phone number or security word."]);
    exit();
}

if (!isset($student['home_airport']) || empty($student['home_airport'])) {
    echo json_encode(["success" => false, "message" => "home_airport is missing from database."]);
    exit();
}

// ✅ Send Clean JSON Output
echo json_encode([
    "success" => true,
    "student_id" => $student["student_id"],
    "student_name" => $student["student_name"],
    "home_airport" => $student["home_airport"] ?? "Unknown",
    "current_lesson" => $student["current_lesson"] ?? null
]);
exit();
