<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== CONFIG =====
$apiKey = 'v1V1bUJBLIH159WZbb0R7V.edvPw4JHSXPfFaluV8OImzjOnW3Ummru2dGqBuG0';
$deviceId = 'IGP119b04410'; // <- Replace this with your real device ID from dashboard
$baseUrl = 'https://api.igloodeveloper.co/v2';

function log_debug($msg) {
    file_put_contents(__DIR__ . "/log.txt", "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// ===== GENERATE AlgoPIN =====
function generate_algo_pin($apiKey, $deviceId, $baseUrl) {
    $now = time();
    $start = gmdate("Y-m-d\TH:i:s\Z", $now);
    $end = gmdate("Y-m-d\TH:i:s\Z", $now + 3600); // 1 hour PIN

    $data = json_encode([
        'device_id' => $deviceId,
        'start_date' => $start,
        'end_date' => $end,
        'pin_type' => 'algo',
        'timezone' => 'UTC'
    ]);

    $url = $baseUrl . '/pins/generate-algo';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
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

// ===== RUN =====
list($status, $result) = generate_algo_pin($apiKey, $deviceId, $baseUrl);

if ($status === 200 && isset($result['pin'])) {
    echo "<h2>🔐 AlgoPIN: <strong>{$result['pin']}</strong></h2>";
    echo "<p>Valid from now until 1 hour from now.</p>";
    log_debug("✅ AlgoPIN created: {$result['pin']}");
} else {
    echo "<h3>❌ AlgoPIN creation failed (status $status)</h3>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    log_debug("❌ Error ($status): " . json_encode($result));
}
?>
