<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once PISTON_SPINNER_LOCAL_DBCONNECT;

if (SPINNER_ALLOW_ORIGIN) {
  header('Access-Control-Allow-Origin: ' . SPINNER_ALLOW_ORIGIN);
  header('Access-Control-Allow-Methods: GET, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
}

try {
  $prefix = SPINNER_DB_PREFIX;
  $profile_key = preg_replace('/[^a-z_]/i', '', ($_GET['profile_key'] ?? 'standard_nudge'));

  $stmt = $pdo->prepare("SELECT `key`, name, slices_json
                         FROM {$prefix}spin_profiles
                         WHERE `key`=? AND active=1");
  $stmt->execute([$profile_key]);
  $row = $stmt->fetch();
  if (!$row) {
    echo json_encode(['ok'=>false,'msg'=>'Profile not found or inactive']); exit;
  }

  $slices = json_decode($row['slices_json'], true);
  if (!is_array($slices) || !count($slices)) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid slices_json']); exit;
  }

  echo json_encode(['ok'=>true,'key'=>$row['key'],'name'=>$row['name'],'slices'=>$slices]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
