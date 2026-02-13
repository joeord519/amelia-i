<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== CONFIG =====
$apiKey = 'v1V1bUJBLIH159WZbb0R7V.edvPw4JHSXPfFaluV8OImzjOnW3Ummru2dGqBuG0';
$baseUrl = 'https://api.igloodeveloper.co/v2';

// ===== Make Initial GET Request to Root or /devices =====
function probe($url, $apiKey) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-IGLOOCOMPANY-APIKEY: $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($response, true)];
}

// === TRY ROOT FIRST
list($statusRoot, $dataRoot) = probe($baseUrl, $apiKey);

// === TRY /devices IF ROOT FAILS
$deviceUrl = $baseUrl . '/devices';
list($statusDevices, $dataDevices) = probe($deviceUrl, $apiKey);

echo "<h2>🔍 Igloohome API Probe</h2>";
echo "<h3>Root Endpoint Status: $statusRoot</h3>";
echo "<pre>" . json_encode($dataRoot, JSON_PRETTY_PRINT) . "</pre>";

echo "<hr><h3>/devices Endpoint Status: $statusDevices</h3>";
echo "<pre>" . json_encode($dataDevices, JSON_PRETTY_PRINT) . "</pre>";
?>
