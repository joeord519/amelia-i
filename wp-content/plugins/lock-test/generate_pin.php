<?php
// lock-test/generate_pin.php (refined with SSL bypass for dev)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== CONFIG =====
$clientId = '6c12jko88grds9vsff2oitfllo';
$clientSecret = '18nrn4gsajs35ieckjju86shikhju5h839ropmnabds6silsfnok';
$deviceId = 'IGP119b04410';
$baseUrl = 'https://api.igloohome.co';

// ===== TOKEN FETCH =====
function getAccessToken($clientId, $clientSecret, $baseUrl) {
    $url = $baseUrl . '/oauth/token';
    $data = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // <== Bypass SSL
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // <== Bypass SSL
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    echo "<h4>💬 CURL DEBUG LOG:</h4><pre>" . htmlspecialchars($verboseLog) . "</pre>";
    echo "<h4>🔍 Token Response (HTTP $httpCode)</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    $res = json_decode($response, true);
    return $res['access_token'] ?? null;
}

// ===== USE TOKEN =====
$accessToken = getAccessToken($clientId, $clientSecret, $baseUrl);
if (!$accessToken) {
    die("<p style='color:red;'>❌ Failed to fetch access token.</p>");
}

// ===== BUILD PIN REQUEST =====
$start = gmdate("Y-m-d\TH:i:s\Z");
$end = gmdate("Y-m-d\TH:i:s\Z", time() + 3600);

$data = json_encode([
    "device_id" => $deviceId,
    "type" => "pin",
    "start_time" => $start,
    "end_time" => $end,
    "name" => "Piston Checkout",
    "timezone" => "UTC"
]);

$url = $baseUrl . '/access-keys';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // <== Bypass SSL
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // <== Bypass SSL

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// ===== OUTPUT =====
if ($httpCode === 201 && isset($result['pin_code'])) {
    echo "<h2>🔐 PIN Code Generated: <strong>{$result['pin_code']}</strong></h2>";
    echo "<p>Valid from $start to $end (UTC)</p>";
} else {
    echo "<h3>❌ Failed to create PIN (Status: $httpCode)</h3>";
    echo "<h4>Raw Response:</h4><pre>" . htmlspecialchars($response) . "</pre>";
    echo "<h4>Parsed JSON:</h4><pre>" . print_r($result, true) . "</pre>";
}
?>

