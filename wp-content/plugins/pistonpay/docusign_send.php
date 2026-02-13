<?php
// /wp-content/plugins/pistonpay/docusign_send.php
// Minimal DocuSign helpers (JWT) + sender with prefilled tabs.

function ds_config() {
  static $cfg;
  if (!$cfg) $cfg = require __DIR__ . '/docusign_config.php';
  return $cfg;
}

function ds_http_post($url, $payload, $headers = []) {
  $ch = curl_init($url);
  if (is_array($payload)) {
    $payload = http_build_query($payload);
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
  } else {
    $headers[] = 'Content-Type: application/json';
  }
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 45,
  ]);
  $out = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err) throw new Exception("cURL error: $err");
  $json = json_decode($out, true);
  return $json ?: ['http_code' => $code, 'raw' => $out];
}

function ds_http_json($method, $url, $access_token, $body = null) {
  $ch = curl_init($url);
  $headers = [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
  ];
  $opts = [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 45,
  ];
  if (!is_null($body)) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
  curl_setopt_array($ch, $opts);
  $out = curl_exec($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($err) throw new Exception("cURL error: $err");
  $json = json_decode($out, true);
  if ($code >= 400) {
    error_log("DocuSign error ($code): $out");
    throw new Exception('DocuSign API error');
  }
  return $json ?: [];
}

function ds_jwt_token() {
  $c = ds_config();
  $now = time();
  $jwt_header = rtrim(strtr(base64_encode(json_encode(['alg'=>'RS256','typ'=>'JWT'])), '+/', '-_'), '=');
  $jwt_claims = rtrim(strtr(base64_encode(json_encode([
    'iss' => $c['integration_key'],
    'sub' => $c['user_id'],
    'aud' => parse_url($c['oauth_host'], PHP_URL_HOST),
    'iat' => $now,
    'exp' => $now + 3600,
    'scope' => $c['impersonation_scope'],
  ])), '+/', '-_'), '=');
  $data = $jwt_header . '.' . $jwt_claims;

  $privateKey = @file_get_contents($c['private_key_path']);
  if ($privateKey === false) throw new Exception('Private key not found at '.$c['private_key_path']);
  $pkey = openssl_pkey_get_private($privateKey);
  if (!$pkey) throw new Exception('Unable to load private key (format/permissions?)');

  openssl_sign($data, $sig, $pkey, OPENSSL_ALGO_SHA256);
  $jwt = $data . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

  $res = ds_http_post($c['oauth_host'].'/oauth/token', [
    'assertion' => $jwt,
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
  ]);
  if (empty($res['access_token'])) {
    error_log('DocuSign JWT error: '.json_encode($res));
    throw new Exception('DocuSign auth failed (JWT)');
  }
  return $res['access_token'];
}

/**
 * Send an envelope from a template to the Student (email route) with prefilled tabs.
 * Returns envelopeId on success.
 *
 * Required DocuSign Template setup:
 *  - Role name: "Student"
 *  - Text tabs with tabLabels exactly:
 *      StudentFullName, StudentEmail, SelectedWeeks, AmountPaid
 */
function docusign_send_timebuilding_envelope(
  string $studentName,
  string $studentEmail,
  string $weeksText,
  $amountPaid // float|int|string
) {
  $c = ds_config();
  $token = ds_jwt_token();

  $envelopeDefinition = [
    'templateId' => $c['template_id'],
    'templateRoles' => [[
      'roleName' => 'Student',
      'name'     => $studentName ?: 'Time Building Student',
      'email'    => $studentEmail,
      // DO NOT set clientUserId here -> keeps EMAIL delivery enabled
      'tabs' => [
        'textTabs' => array_values(array_filter([
          // Prefill the 4 fields if non-empty; DocuSign ignores unknown tabLabels
          $studentName  ? ['tabLabel' => 'StudentFullName', 'value' => $studentName] : null,
          $studentEmail ? ['tabLabel' => 'StudentEmail',    'value' => $studentEmail] : null,
          $weeksText    ? ['tabLabel' => 'SelectedWeeks',    'value' => $weeksText] : null,
          isset($amountPaid) ? ['tabLabel' => 'AmountPaid', 'value' => (is_numeric($amountPaid) ? ('$'.number_format((float)$amountPaid, 2)) : (string)$amountPaid)] : null,
        ])),
      ],
    ]],
    'status' => 'sent', // send immediately via email
    // Optional subject/BL: uncomment to customize
    // 'emailSubject' => 'Piston Aviation Time Building Agreement',
    // 'emailBlurb'   => 'Please review and sign your Time Building Agreement.',
  ];

  $url = rtrim($c['base_path'],'/')."/v2.1/accounts/{$c['account_id']}/envelopes";
  $resp = ds_http_json('POST', $url, $token, $envelopeDefinition);
  return $resp['envelopeId'] ?? null;
}

/**
 * Ensure recipient has a clientUserId (for embedded signing) AFTER the envelope is already sent.
 * This lets us both email the student AND offer embedded signing on the success page.
 * Returns the recipientIdGuid for the Student role.
 */
function docusign_ensure_embedded_recipient(string $envelopeId, string $studentEmail, string $clientUserId = 'timebuilding-embedded') {
  $c = ds_config();
  $token = ds_jwt_token();
  $base = rtrim($c['base_path'],'/')."/v2.1/accounts/{$c['account_id']}/envelopes/{$envelopeId}";

  // 1) Get recipients to find the Student
  $recips = ds_http_json('GET', $base . '/recipients', $token);
  $student = null;
  if (!empty($recips['signers'])) {
    foreach ($recips['signers'] as $s) {
      if (strcasecmp($s['email'] ?? '', $studentEmail) === 0 || strcasecmp($s['roleName'] ?? '', 'Student') === 0) {
        $student = $s; break;
      }
    }
  }
  if (!$student) throw new Exception('Student recipient not found on envelope');

  // If already has clientUserId, we're good
  if (!empty($student['clientUserId'])) {
    return $student['recipientIdGuid'] ?? $student['recipientId'] ?? null;
  }

  // 2) Update recipient to set clientUserId
  $body = [
    'signers' => [[
      'recipientId' => $student['recipientId'],
      'name'        => $student['name'] ?? 'Student',
      'email'       => $student['email'] ?? $studentEmail,
      'clientUserId'=> $clientUserId,
      'routingOrder'=> $student['routingOrder'] ?? '1',
      'roleName'    => $student['roleName'] ?? 'Student',
    ]],
  ];
  ds_http_json('PUT', $base . '/recipients', $token, $body);

  return $student['recipientIdGuid'] ?? $student['recipientId'] ?? null;
}

/**
 * Create a Recipient View (embedded signing) URL for the Student.
 * Requires the recipient to have clientUserId set (use docusign_ensure_embedded_recipient first).
 */
function docusign_create_recipient_view(string $envelopeId, string $studentName, string $studentEmail, string $returnUrl, string $clientUserId = 'timebuilding-embedded') {
  $c = ds_config();
  $token = ds_jwt_token();
  $url = rtrim($c['base_path'],'/')."/v2.1/accounts/{$c['account_id']}/envelopes/{$envelopeId}/views/recipient";

  $view = [
    'authenticationMethod' => 'none',
    'email'       => $studentEmail,
    'userName'    => $studentName ?: 'Student',
    'clientUserId'=> $clientUserId,
    'returnUrl'   => $returnUrl,
  ];
  $resp = ds_http_json('POST', $url, $token, $view);
  return $resp['url'] ?? null;
}
