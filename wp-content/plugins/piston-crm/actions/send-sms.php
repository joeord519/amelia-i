<?php
require_once(__DIR__ . '/../lib/comm-functions.php');
require_once(__DIR__ . '/../lib/lead-functions.php');
require_once(__DIR__ . '/../lib/sakari.php');

$lead_id = $_POST['lead_id'] ?? null;
$message = trim($_POST['message'] ?? '');

if ($lead_id && $message) {
  $lead = getLeadById($lead_id);
  if ($lead) {
    // Rotate SMS footer (if message doesn't already mention 'stop')
    $footers = [
      "Questions? Just reply. STOP to opt out.",
      "This is a real human text. Text STOP to stop."
    ];

    if (!str_contains(strtolower($message), 'stop')) {
     $footer = $footers[array_rand($footers)];
     $message .= "\n\n—\n" . $footer;
    }

    $success = sendSakariSMS($lead['phone'], $message);

    if ($success) {
      logCommHistory($lead_id, 'sms', 'outbound', $message);
    }
  }
}

header("Location: ../lead-detail.php?id=$lead_id");
exit;
