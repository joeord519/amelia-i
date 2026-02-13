<?php
// ✅ Ensure this script runs independently or within WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// ✅ Database Credentials
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';
$username = 'uizsmtjki2wdx';
$password = '7w26g#@$>iD5';

// ✅ Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/db_connect-error.log');

try {
    // ✅ Establish Database Connection
    $conn = new mysqli($host, $username, $password, $dbname);

    // ✅ Check Connection
    if ($conn->connect_error) {
        error_log("❌ Database Connection Failed: " . $conn->connect_error);
        die(json_encode(["error" => "Database connection failed"]));
    }
    
    error_log("✅ Database Connected Successfully");

} catch (Exception $e) {
    error_log("❌ Exception in db_connect.php: " . $e->getMessage());
    die(json_encode(["error" => "Exception: " . $e->getMessage()]));
}
?>
