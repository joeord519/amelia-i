<?php
require_once(__DIR__ . '/../lib/comm-functions.php');
require_once(__DIR__ . '/../lib/lead-functions.php');
require_once(__DIR__ . '/../lib/mailgun.php');

$lead_id  = $_POST['lead_id'] ?? null;
$message  = trim($_POST['message'] ?? '');
$subject  = trim($_POST['subject'] ?? 'Message from Piston Aviation');
$replyTo  = trim($_POST['reply_to'] ?? 'replies@mail.flypiston.com'); // Default fallback

if ($lead_id && $message) {
  $lead = getLeadById($lead_id);
  if ($lead) {
    // 🔗 Build unsubscribe link
    $unsubscribeUrl = "https://flypiston.com/crm/unsubscribe.php?lead_id={$lead['id']}&email=" . urlencode($lead['email']);

    // 🔁 Replace token in message
    $finalMessage = str_replace("{{unsubscribe_link}}", $unsubscribeUrl, $message);

    // ✉️ Send HTML email via Mailgun
    $success = sendMailgunEmail($lead['email'], $subject, $finalMessage, $replyTo);

    if ($success) {
      // 🧼 Clean log version of message
      $cleanText = strip_tags($finalMessage);
      $cleanText = preg_replace('/Unsubscribe from future emails.*/i', '', $cleanText);
      $logEntry = "Subject: {$subject}\n\n" . trim($cleanText);

      logCommHistory($lead_id, 'email', 'outbound', $logEntry);
    }
  }
}

// ⏎ Redirect to lead detail
header("Location: ../lead-detail.php?id=$lead_id");
exit;



