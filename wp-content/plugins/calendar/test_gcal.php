<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Adjusted relative paths to match your structure
require_once("appointment-booking/google_calendar_push.php");
require_once("db_connect.php");

$conn = getDB();

// Replace with valid connected CFI ID
$test_cfi_id = 4;
$date = date("Y-m-d");

$result = getCfiGoogleBusyTimes($conn, $test_cfi_id, $date);

header('Content-Type: application/json');
echo json_encode($result);


