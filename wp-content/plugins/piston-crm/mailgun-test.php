<?php
// ✅ Include the Mailgun function
require_once(__DIR__ . '/lib/mailgun.php');

$to = 'joe@flypiston.com';  // Replace with your email
$body = "This is a test email from Mailgun.";

if (function_exists('sendMailgunEmail')) {
  $success = sendMailgunEmail($to, $body);

  if ($success) {
    echo "✅ Email sent successfully!";
  } else {
    echo "❌ Email failed. Check mailgun-debug.log.";
  }
} else {
  echo "❌ ERROR: sendMailgunEmail() is not defined!";
}
?>


