<?php
$to = "joe@flypiston.com";
$subject = "Test Email from Piston Aviation";
$message = "This is a test email.";
$headers = "From: no-reply@amelia-i.com\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Email failed to send.";
}
?>
