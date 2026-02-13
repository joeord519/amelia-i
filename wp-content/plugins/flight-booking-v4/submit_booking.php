<?php
require 'db_connect.php';

// ✅ Clear any previous output to prevent JSON errors
ob_clean();

// ✅ Enable full error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

header('Content-Type: application/json');

// 🔍 Log incoming POST data
file_put_contents('debug_booking.txt', "🔍 Incoming POST Data:\n" . print_r($_POST, true) . "\n", FILE_APPEND);

// ✅ Step 1: Verify Database Connection
$query = "SELECT DATABASE()";
$result = $pdo->query($query);
$dbName = $result->fetchColumn();
file_put_contents('debug_booking.txt', "🔍 Connected to Database: " . $dbName . "\n", FILE_APPEND);

// ✅ Step 2: Verify Table Name
$query = "SHOW TABLES LIKE 'wp_flight_schedule'";
$result = $pdo->query($query);
$tableExists = $result->fetchColumn();
file_put_contents('debug_booking.txt', "🔍 Checking Table wp_flight_schedule: " . ($tableExists ? "Exists" : "Does NOT Exist!") . "\n", FILE_APPEND);

if (!$tableExists) {
    echo json_encode(["error" => "Table `wp_flight_schedule` does not exist in the database."]);
    exit;
}

// ✅ Step 3: Verify Column Exists in `wp_flight_schedule`
$query = "SHOW COLUMNS FROM wp_flight_schedule LIKE 'tail_number'";
$result = $pdo->query($query);
$columnExists = $result->fetchColumn();
file_put_contents('debug_booking.txt', "🔍 Checking Column tail_number: " . ($columnExists ? "Exists" : "Does NOT Exist!") . "\n", FILE_APPEND);

if (!$columnExists) {
    echo json_encode(["error" => "Column `tail_number` does not exist in `wp_flight_schedule`."]);
    exit;
}

// ✅ Step 4: Validate Required Parameters
$tail_number = trim($_POST['tail_number'] ?? '');
$start_date = trim($_POST['date'] ?? '');
$start_time = trim($_POST['time'] ?? '');
$student_id = trim($_POST['student_id'] ?? '');
$cfi_id = trim($_POST['cfi_id'] ?? ''); // Allow CFI to be optional
$flight_type = trim($_POST['lesson_type'] ?? 'unknown');

// ✅ Log received values before processing
file_put_contents('debug_booking.txt', "🔍 Received Values - Date: '$start_date', Time: '$start_time'\n", FILE_APPEND);

// ✅ Extract only time portion if `$_POST['time']` contains full datetime
if (strpos($start_time, ' ') !== false) {
    $start_time = explode(' ', $start_time)[1]; // ✅ Extracts only HH:MM:SS
}

file_put_contents('debug_booking.txt', "🔍 Extracted Date: '$start_date', Extracted Time: '$start_time'\n", FILE_APPEND);

// ✅ Ensure Proper `DATETIME` Format for MySQL
if (!empty($start_date) && !empty($start_time)) {
    $start_datetime = sprintf('%s %s', $start_date, $start_time); // ✅ Guarantees correct format
    $end_datetime = date('Y-m-d H:i:s', strtotime("$start_datetime +2 hours")); // ✅ Adds 2 hours correctly
} else {
    file_put_contents('debug_booking.txt', "❌ Missing or invalid Date/Time. Date='$start_date', Time='$start_time'\n", FILE_APPEND);
    echo json_encode(["error" => "Invalid date or time."]);
    exit;
}

// ✅ Log missing parameters
$missing_params = [];
if (!$tail_number) $missing_params[] = "tail_number";
if (!$student_id) $missing_params[] = "student_id";
if (!$start_datetime) $missing_params[] = "start_datetime";
if (!$end_datetime) $missing_params[] = "end_datetime";

if (!empty($missing_params)) {
    file_put_contents('debug_booking.txt', "❌ Missing Parameters: " . implode(", ", $missing_params) . "\n", FILE_APPEND);
    echo json_encode(["error" => "Missing required parameters: " . implode(", ", $missing_params)]);
    exit;
}

// ✅ Step 5: Check if Slot is Available Before Booking
file_put_contents('debug_booking.txt', "🔍 Checking slot availability for Aircraft: $tail_number, Time: $start_datetime\n", FILE_APPEND);

$check_query = "SELECT COUNT(*) AS existing FROM wp_flight_schedule WHERE tail_number = :tail_number AND start_time = :start_time AND slot_status = 'Available'";
$check_stmt = $pdo->prepare($check_query);
$check_stmt->bindValue(':tail_number', $tail_number, PDO::PARAM_STR);
$check_stmt->bindValue(':start_time', $start_datetime, PDO::PARAM_STR);
$check_stmt->execute();
$check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);

$existingSlots = $check_result['existing'] ?? 0; // ✅ Treat NULL as 0

if ($existingSlots == 0) {
    file_put_contents('debug_booking.txt', "⚠️ No existing records, assuming slot is available\n", FILE_APPEND);
}

// ✅ Step 6: Insert the Booking
file_put_contents('debug_booking.txt', "🔍 Final start_datetime before insert: " . var_export($start_datetime, true) . "\n", FILE_APPEND);
file_put_contents('debug_booking.txt', "🔍 Final end_datetime before insert: " . var_export($end_datetime, true) . "\n", FILE_APPEND);
file_put_contents('debug_booking.txt', "🔍 Final SQL Query Before Execution\n", FILE_APPEND);
file_put_contents('debug_booking.txt', "🔍 SQL Parameters: Tail Number: " . ($tail_number ?? "MISSING") . 
    ", Student ID: " . ($student_id ?? "MISSING") . 
    ", CFI ID: " . ($cfi_id ?? "MISSING") . 
    ", Flight Type: " . ($flight_type ?? "MISSING") . 
    ", Start Time: " . ($start_datetime ?? "MISSING") . 
    ", End Time: " . ($end_datetime ?? "MISSING") . "\n", FILE_APPEND);

try {
    $insert_query = "
    INSERT INTO wp_flight_schedule (tail_number, student_id, cfi_id, start_time, end_time, flight_type, slot_status, created_at, updated_at)
    VALUES (:tail_number, :student_id, :cfi_id, STR_TO_DATE(:start_time, '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(:end_time, '%Y-%m-%d %H:%i:%s'), :flight_type, 'Booked', NOW(), NOW())
    ";

    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->bindValue(':tail_number', $tail_number, PDO::PARAM_STR);
    $insert_stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
    $insert_stmt->bindValue(':cfi_id', $cfi_id, PDO::PARAM_STR);
    $insert_stmt->bindValue(':flight_type', $flight_type, PDO::PARAM_STR);
    $insert_stmt->bindValue(':start_time', $start_datetime, PDO::PARAM_STR);
    $insert_stmt->bindValue(':end_time', $end_datetime, PDO::PARAM_STR);
    $insert_stmt->execute();

    file_put_contents('debug_booking.txt', "✅ Booking confirmed successfully!\n", FILE_APPEND);
    echo json_encode(["success" => true, "message" => "Booking confirmed successfully."]);

} catch (PDOException $e) {
    file_put_contents('debug_booking.txt', "❌ SQL Error: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('debug_booking.txt', "🔍 Final start_datetime: " . $start_datetime . "\n", FILE_APPEND);
    
    header('Content-Type: application/json');
    echo json_encode(["error" => "Database error: " . $e->getMessage()], JSON_PRETTY_PRINT);
    exit;
}
?>
