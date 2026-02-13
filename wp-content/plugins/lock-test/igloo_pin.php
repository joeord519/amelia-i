<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== CONFIG =====
$apiKey = 'v1V1bUJBLIH159WZbb0R7V.edvPw4JHSXPfFaluV8OImzjOnW3Ummru2dGqBuG0';
$lockId = 'IGP119b04410'; // N2723B
$baseUrl = 'https://api.igloodeveloper.co/v2';

function log_debug($msg) {
    file_put_contents(__DIR__ . "/log.txt", "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

// ===== GENERATE PIN REQUEST =====
function generate_pin($apiKey, $lockId, $baseUrl) {
    $now = time();
    $start = gmdate("Y-m-d\TH:i:s\Z", $now);
    $end = gmdate("Y-m-d\TH:i:s\Z", $now + 3600); // 1 hour later

    $data = json_encode([
        'name' => 'Test PIN',
        'type' => 'duration',
        'start_date' => $start,
        'end_date' => $end
    ]);

    $url = $baseUrl . "/locks/$lockId/pins";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-IGLOOCOMPANY-APIKEY: $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, json_decode($response, true)];
}

// ===== RUN =====
list($status, $result) = generate_pin($apiKey, $lockId, $baseUrl);

if ($status === 200 && isset($result['pin'])) {
    echo "<h2>🔐 PIN Created: <strong>{$result['pin']}</strong></h2>";
    echo "<p>Valid from now until 1 hour from now.</p>";
    log_debug("✅ PIN generated: {$result['pin']}");
} else {
    echo "<h3>❌ Failed to create PIN</h3>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    log_debug("❌ Error ($status): " . json_encode($result));
}
?>

