<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php'; // Make sure AWS SDK is installed here

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

header('Content-Type: application/json');

$bucket = 'piston-logbooks';
$region = 'us-east-2'; // Confirm this matches your actual region
$accessKey = 'AKIAZ3MGNBSXJ2IEQRZH'; // REPLACE
$secretKey = 'wuC/yzCEp/cQXxOCK5tyOpuzQjVu4EdoEcJNOAmo'; // REPLACE

try {
  $input = json_decode(file_get_contents('php://input'), true);
  $filename = basename($input['filename']);
  $filetype = $input['filetype'] ?? 'application/octet-stream';

  $s3 = new S3Client([
    'region' => $region,
    'version' => 'latest',
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
  ]);

  $cmd = $s3->getCommand('PutObject', [
    'Bucket' => $bucket,
    'Key' => "logbooks/$filename",
    'ContentType' => $filetype,
    'ACL' => 'private'
  ]);

  $request = $s3->createPresignedRequest($cmd, '+60 minutes');

  echo json_encode([
    'url' => (string) $request->getUri(),
    'final_url' => "https://{$bucket}.s3.{$region}.amazonaws.com/logbooks/{$filename}"
  ]);
} catch (AwsException $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getAwsErrorMessage()]);
}

