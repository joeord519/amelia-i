<?php
/**
 * Base44 Tach Bridge — sends total_hours (tach) updates to Base44 AFM
 * Location: wp-content/plugins/checkout.basic/afm_bridge.php
 */

/**
 * Endpoint from Base44 (Igor's function)
 */
const TACH_ENDPOINT_URL = 'https://68b70ceea9ef88cb5680ab07.base44.com/functions/receiveExternalTachTime';

/**
 * Ensure TACH_API_KEY is available.
 * - If WordPress loaded wp-config, it's already defined.
 * - If this script is hit directly, load secure-config manually.
 */
if (!defined('TACH_API_KEY')) {
    // /wp-content/plugins/checkout.basic -> dirname(dirname(__DIR__)) = /wp-content
    $configPath = dirname(dirname(__DIR__)) . '/secure-config/pistonafm-config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

/**
 * Send a total_hours (end tach) update to Base44 AFM.
 *
 * @param string      $tailNumber   e.g. "N2723B"
 * @param float|int   $totalHours   e.g. 1532.6 (your end tach)
 * @param int|string  $flightLogId  Optional, for your own debugging/audit
 *
 * @return array [ 'success' => bool, 'http_code' => int, 'response' => mixed ]
 */
function afm_send_total_hours($tailNumber, $totalHours, $flightLogId = null)
{
    // Clean inputs
    $tailNumber = trim((string) $tailNumber);
    $totalHours = is_numeric($totalHours) ? (float) $totalHours : null;

    // Basic validation
    if ($tailNumber === '' || $totalHours === null) {
        error_log('[Base44 Tach] Invalid payload: tail=' . $tailNumber . ' total_hours=' . print_r($totalHours, true));
        return [
            'success'   => false,
            'http_code' => 0,
            'response'  => 'Invalid tail number or total_hours',
        ];
    }

    // Ensure API key is present
    if (!defined('TACH_API_KEY') || empty(TACH_API_KEY)) {
        error_log('[Base44 Tach] TACH_API_KEY is not defined or empty.');
        return [
            'success'   => false,
            'http_code' => 0,
            'response'  => 'TACH_API_KEY not configured',
        ];
    }

        // Debug: see what DNS resolution looks like from this server
    $resolved = gethostbyname(parse_url(TACH_ENDPOINT_URL, PHP_URL_HOST));
    error_log('[Base44 Tach] DNS test: host=' . parse_url(TACH_ENDPOINT_URL, PHP_URL_HOST) . ' => ' . $resolved);
    
    // Build payload that Igor's function expects
    $payload = [
        'tail_number' => $tailNumber,
        'total_hours' => $totalHours,
        // Extra metadata (ignored by his current function but useful later)
        'source_system'   => 'amelia-checkout',
        'flight_log_id'   => $flightLogId,
        'recorded_at_utc' => gmdate('c'),
    ];

    $ch = curl_init(TACH_ENDPOINT_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . 'Bearer ' . TACH_API_KEY,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($rawResponse === false) {
        $curlErr = curl_error($ch);
        curl_close($ch);

        error_log('[Base44 Tach] cURL error: ' . $curlErr);

        return [
            'success'   => false,
            'http_code' => 0,
            'response'  => $curlErr,
        ];
    }

    curl_close($ch);

    $decoded = json_decode($rawResponse, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Base44 Tach] Invalid JSON response: ' . $rawResponse);

        return [
            'success'   => false,
            'http_code' => $httpCode,
            'response'  => $rawResponse,
        ];
    }

    // Success: Igor's function returns { success: true, message, aircraft: {...} }
    if ($httpCode >= 200 && $httpCode < 300 && !empty($decoded['success'])) {
        error_log(sprintf(
            '[Base44 Tach] OK: tail=%s total_hours=%.2f http=%d',
            $tailNumber,
            $totalHours,
            $httpCode
        ));
        return [
            'success'   => true,
            'http_code' => $httpCode,
            'response'  => $decoded,
        ];
    }

    // Failure (400/401/404/500)
    error_log(sprintf(
        '[Base44 Tach] FAILED: tail=%s total_hours=%.2f http=%d resp=%s',
        $tailNumber,
        $totalHours,
        $httpCode,
        print_r($decoded, true)
    ));

    return [
        'success'   => false,
        'http_code' => $httpCode,
        'response'  => $decoded,
    ];
}
