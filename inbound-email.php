<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 🛠️ NEW: Log full headers and raw input
$headers = getallheaders();
$logEntry = "HEADERS:\n" . print_r($headers, true) . "\n";
$logEntry .= "BODY:\n" . file_get_contents("php://input") . "\n---\n";

file_put_contents(__DIR__ . '/wp-content/plugins/piston-crm/mailgun-inbound.log', $logEntry, FILE_APPEND);

// Stop here for now
exit('Logged test payload');




