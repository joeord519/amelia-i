<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // ✉️ SMTP Setup
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'joe@flypiston.com';      // 🔁 YOUR EMAIL
    $mail->Password = 'nvrb nkan eaka bdjv';         // 🔁 YOUR APP PASSWORD
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    // ✅ Debug Output to File
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        file_put_contents(__DIR__ . '/logs/email-tester-debug.txt', $str . "\n", FILE_APPEND);
    };

    // 📩 Email Setup
    $mail->setFrom('joe@flypiston.com', 'Piston Aviation');  // 🔁 SAME EMAIL
    $mail->addAddress('joe@flypiston.com');       // 🔁 DESTINATION

    $mail->isHTML(true);
    $mail->Subject = '🚀 Test Email from Piston Aviation';
    $mail->Body = '<h2 style="color:green;">Success!</h2><p>If you see this, email is working.</p>';
    $mail->AltBody = 'If you see this, email is working.';

    $mail->send();
    echo "<h2 style='color:green;'>✅ Test email sent successfully!</h2>";
    echo "<p>Check your inbox at <strong>your_personal_email@example.com</strong>.</p>";
    echo "<p>SMTP logs saved to <code>/logs/email-tester-debug.txt</code></p>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Email failed to send</h2>";
    echo "<p>Error: {$mail->ErrorInfo}</p>";
}
