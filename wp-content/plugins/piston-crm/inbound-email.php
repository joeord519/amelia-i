<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/mailgun-inbound.log', print_r($_POST, true), FILE_APPEND);

require_once(__DIR__ . '/db_connect.php');
require_once(__DIR__ . '/lib/lead-functions.php');
require_once(__DIR__ . '/lib/comm-functions.php');

// Mailgun sends as multipart/form-data
$sender = strtolower(trim($_POST['sender'] ?? ''));
$body = trim($_POST['stripped-text'] ?? '') ?: strip_tags($_POST['body-html'] ?? '');

if (!$sender || !$body) {
  http_response_code(400);
  echo 'Missing sender or message body';
  exit;
}

$lead = getLeadByEmail($sender);
if (!$lead) {
  http_response_code(404);
  echo 'Lead not found';
  exit;
}

// Save plain text only to comm history
logCommHistory($lead['id'], 'email', 'inbound', $body);

http_response_code(200);
echo 'Reply logged';

