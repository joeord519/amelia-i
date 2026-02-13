<?php
require_once 'send_sms_sakari.php';

$testPhone = '+16363283750';
$message = "✈️ Your flight with CFI Chris is available at 2pm. Reply YES to book. Reply STOP to unsubscribe.";

echo "<pre>Running test_send.php\n";

// Call the function and capture debug info
ob_start();
$result = sendSakariSMS($testPhone, $message);
$debugOutput = ob_get_clean();

echo $debugOutput;
echo $result ? "✅ Message sent!" : "❌ Message failed!";
echo "</pre>";


