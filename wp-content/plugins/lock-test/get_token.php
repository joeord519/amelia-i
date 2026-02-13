<?php
// lock-test/get_token.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$clientId = '6c12jko88grds9vsff2oitfllo';
$clientSecret = '18nrn4gsajs35ieckjju86shikhju5h839ropmnabds6silsfnok';

$url = 'https://api.iglooaccess.co/v2/oauth/token';

$data = [
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (isset($tokenData['access_token'])) {
    echo "<h2>✅ Access Token Retrieved:</h2>";
    echo "<pre>{$tokenData['access_token']}</pre>";
} else {
    echo "<h2>❌ Failed to get token</h2>";
    echo "<pre>" . print_r($tokenData, true) . "</pre>";
}
?>
