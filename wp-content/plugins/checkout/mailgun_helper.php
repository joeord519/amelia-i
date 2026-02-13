<?php
function sendViaMailgunAPI($to, $name, $subject, $htmlBody) {
    $apiKey = 'api:71d471182ca5a3a56c2ed8d94c537137-e71583bb-1c483164';
    $domain = 'mg.amelia-i.com';
    $url = "https://api.mailgun.net/v3/$domain/messages";

    $postData = [
        'from'    => 'Piston Aviation <postmaster@mg.amelia-i.com>',
        'to'      => "$name <$to>",
        'subject' => $subject,
        'html'    => $htmlBody,
        'o:tracking-clicks' => 'no'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    $result = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($info['http_code'] === 200) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $error ?: $result];
    }
}
