<?php
require_once('../db_connect.php');
$conn = getDB();

 $client_id       = "179521123899-pa69m35lqmhi8usqvcgg0m2eeopjkj9m.apps.googleusercontent.com";
 $client_secret   = "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";

$stmt = $conn->query("SELECT cfi_id, first_name, last_name, google_refresh_token FROM wp_cfis WHERE google_refresh_token IS NOT NULL");
$cfis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
foreach ($cfis as $cfi) {
  $ch = curl_init("https://oauth2.googleapis.com/token");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'refresh_token' => $cfi['google_refresh_token'],
    'grant_type' => 'refresh_token'
  ]));

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $status = ($http_code === 200) ? "✅ VALID" : "❌ INVALID or REVOKED";
  echo "CFI {$cfi['first_name']} {$cfi['last_name']} (ID {$cfi['cfi_id']}): $status\n";
}
echo "</pre>";
?>
