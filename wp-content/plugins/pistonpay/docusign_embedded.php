<?php
// /wp-content/plugins/pistonpay/docusign_embedded.php
// Redirect the student into DocuSign embedded signing after Stripe success.

require_once __DIR__ . '/config.php';        // provides Stripe init + getDB()
require_once __DIR__ . '/docusign_send.php'; // JWT + send/ensure-embedded/view helpers

function redirect_now(string $url) {
  header("Location: $url", true, 302);
  exit;
}

try {
  // 1) Inputs + return URL back to success page
  $sessionId = $_GET['session_id'] ?? '';
  if (!$sessionId) {
    throw new Exception('Missing session_id');
  }
  $returnUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
             . '/wp-content/plugins/pistonpay/payment-success.php?done=1';

  // 2) Get payer info from Stripe session
  $session = \Stripe\Checkout\Session::retrieve($sessionId);
  $email = $session->customer_details->email ?? null;
  $name  = trim(($session->customer_details->name ?? '') ?: 'Time Building Student');

  if (!$email) {
    throw new Exception('No customer email on Stripe session');
  }

  // 3) Find the most recent envelope we logged for this email
  $db = getDB();
  $sel = $db->prepare("SELECT envelope_id FROM wp_tb_envelopes WHERE student_email = ? ORDER BY created_at DESC LIMIT 1");
  $sel->execute([$email]);
  $envelopeId = $sel->fetchColumn();

  if (!$envelopeId) {
    // No envelope logged yet (race or send failed) -> fall back to email flow
    throw new Exception('Envelope not found for this email');
  }

  // 4) Ensure recipient is embedded (set clientUserId after send)
  docusign_ensure_embedded_recipient($envelopeId, $email, 'timebuilding-embedded');

  // 5) Create recipient view URL and redirect into DocuSign
  $viewUrl = docusign_create_recipient_view($envelopeId, $name, $email, $returnUrl, 'timebuilding-embedded');
  if (!$viewUrl) {
    throw new Exception('No recipient view URL returned');
  }

  redirect_now($viewUrl);

} catch (Throwable $e) {
  error_log("DocuSign embedded error: " . $e->getMessage());
  // Graceful fallback: the student still received the DocuSign email
  redirect_now('/wp-content/plugins/pistonpay/payment-success.php?embed_error=1');
}

