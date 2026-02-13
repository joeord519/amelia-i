<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/logs/test-hit.txt', "[" . date('Y-m-d H:i:s') . "] ✅ Test endpoint hit\n", FILE_APPEND);

$subject = $_POST['subject'] ?? 'MISSING_SUBJECT';
$body = $_POST['body'] ?? 'MISSING_BODY';
$sendMode = $_POST['sendMode'] ?? 'MISSING_MODE';

file_put_contents(__DIR__ . '/logs/test-values.txt', print_r([
    'subject' => $subject,
    'body' => $body,
    'sendMode' => $sendMode
], true), FILE_APPEND);

echo "✅ Endpoint working. Received subject: $subject | Mode: $sendMode";
