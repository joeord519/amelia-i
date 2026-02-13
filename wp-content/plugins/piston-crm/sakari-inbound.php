<?php
require_once(__DIR__ . '/lib/db_connect.php');
require_once(__DIR__ . '/lib/lead-functions.php');
require_once(__DIR__ . '/lib/comm-functions.php');

$data = json_decode(file_get_contents("php://input"), true);

$event = $data['Event'] ?? null;
$from  = $data['From'] ?? null;
$body  = trim($data['Body'] ?? '');

if (!$from) {
  http_response_code(400);
  echo 'Missing sender number';
  exit;
}

$lead = getLeadByPhone($from);
if (!$lead) {
  http_response_code(404);
  echo 'Lead not found';
  exit;
}

if ($event === 'contact_opt_out') {
  logCommHistory($lead['id'], 'sms', 'inbound', 'STOP (Carrier-Level Opt Out)');
}

if ($event === 'contact_opt_in') {
  logCommHistory($lead['id'], 'sms', 'inbound', 'START (Carrier-Level Opt In)');
}

// For any other message (like actual replies)
if ($event === 'message_received' && !in_array(strtolower($body), ['stop', 'start'])) {
  logCommHistory($lead['id'], 'sms', 'inbound', $body);
}

http_response_code(200);
echo 'OK';


