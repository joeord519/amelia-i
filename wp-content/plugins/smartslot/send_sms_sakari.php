<?php
require_once 'config.php';

function sendSakariSMS($toPhoneNumber, $messageBody)
{
    // Step 1: Get access token from Sakari
    $tokenUrl = 'https://login.sakari.io/oauth2/token';
    $postData = [
        'client_id' => SAKARI_CLIENT_ID,
        'client_secret' => SAKARI_CLIENT_SECRET,
        'grant_type' => 'client_credentials'
    ];

    $ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$tokenResponse = curl_exec($ch);

if (curl_errno($ch)) {
    error_log("❌ CURL error: " . curl_error($ch));
}
curl_close($ch);


    $tokenData = json_decode($tokenResponse, true);
    if (!isset($tokenData['access_token'])) {
        error_log("❌ Failed to get access token: $tokenResponse");
        return false;
    }

    $accessToken = $tokenData['access_token'];

    // Step 2: Send SMS
    $sendUrl = 'https://api.sakari.io/v1/messages';
    $payload = [
        'phoneNumber' => $toPhoneNumber,
        'message' => $messageBody,
        'fromPhoneNumber' => SAKARI_PHONE_NUMBER
    ];

    $headers = [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ];

    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $sendResponse = curl_exec($ch);
    curl_close($ch);

    $sendResult = json_decode($sendResponse, true);

    if (isset($sendResult['messageId'])) {
    error_log("✅ Message sent to $toPhoneNumber | ID: " . $sendResult['messageId']);
    return true;
} else {
    error_log("❌ Message failed: " . print_r($sendResult, true)); // ← SHOW FULL ERROR RESPONSE
    return false;
}

}
