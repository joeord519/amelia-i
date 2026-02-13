<?php
// ✅ Remove all echo/debug output (keep only error reporting if needed)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Database Credentials
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';
$username = 'uizsmtjki2wdx';
$password = '7w26g#@$>iD5';

$con = mysqli_connect($host, $username, $password, $dbname);

if (!$con) {
    die(json_encode(["success" => false, "message" => "Database connection error: " . mysqli_connect_error()]));
}
?>