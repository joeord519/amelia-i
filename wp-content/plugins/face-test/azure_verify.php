<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Updated DB connection
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';
$username = 'uizsmtjki2wdx';
$password = '7w26g#@$>iD5';

$pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

// ✅ Azure Face API config
$azureEndpoint = "https://piston-face-api.cognitiveservices.azure.com/";
$azureKey = "FoXwTOlGdzh9WfGPUXm6V6HQaYkLwqYJe9CKrWlHs7guFit4KInYJQQJ99BDACYeBjFXJ3w3AAAKACOGvcwA";
$studentId = 177;

if (!function_exists('log_debug')) {
    function log_debug($msg) {
        file_put_contents(__DIR__ . "/log.txt", "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
    }
}

function upload_image($fieldname, $filename) {
    $targetDir = __DIR__ . "/uploads/";
    $imageFileType = strtolower(pathinfo($_FILES[$fieldname]["name"], PATHINFO_EXTENSION));
    $targetFile = $targetDir . $filename . "." . $imageFileType;

    if (!move_uploaded_file($_FILES[$fieldname]["tmp_name"], $targetFile)) {
        die("Error uploading $fieldname.");
    }

    return "https://amelia-i.com/wp-content/plugins/face-test/uploads/" . basename($targetFile);
}

function get_face_id($imageUrl, $azureEndpoint, $azureKey) {
    $url = $azureEndpoint . "face/v1.0/detect?returnFaceId=true";
    $data = json_encode(['url' => $imageUrl]);
    $headers = [
        "Content-Type: application/json",
        "Ocp-Apim-Subscription-Key: $azureKey"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($result, true);
    return $json[0]['faceId'] ?? null;
}

// STEP 1 – Upload Initial Profile Selfie
if (isset($_POST['submit_profile'])) {
    $profileUrl = upload_image('profile_photo', "profile_{$studentId}");
    $stmt = $pdo->prepare("UPDATE wp_students SET profile_photo_url = ? WHERE student_id = ?");
    $stmt->execute([$profileUrl, $studentId]);
    echo "<h3>✅ Initial profile photo saved!</h3><a href='azure_verify.php'>Continue to Match Test</a>";
    exit;
}

// STEP 2 – Upload Comparison Selfie and Verify Match
if (isset($_POST['submit_verify'])) {
    $verifyUrl = upload_image('verify_photo', "verify_{$studentId}");

    $stmt = $pdo->prepare("SELECT profile_photo_url FROM wp_students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch();
    $profileUrl = $row['profile_photo_url'];

    if (!$profileUrl) {
        die("❌ No profile photo found. Please upload the initial selfie first.");
    }

    $faceId1 = get_face_id($profileUrl, $azureEndpoint, $azureKey);
    $faceId2 = get_face_id($verifyUrl, $azureEndpoint, $azureKey);

    if (!$faceId1 || !$faceId2) {
        log_debug("Failed to get faceId: Profile=$faceId1, Verify=$faceId2");
        die("❌ Failed to detect face in one or both images.");
    }

    $verifyData = json_encode(['faceId1' => $faceId1, 'faceId2' => $faceId2]);
    $verifyUrlEndpoint = $azureEndpoint . "face/v1.0/verify";

    $ch = curl_init($verifyUrlEndpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Ocp-Apim-Subscription-Key: $azureKey"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['confidence'])) {
        echo "<h2>🧠 Face Match Confidence: " . round($result['confidence'] * 100, 2) . "%</h2>";
    } else {
        log_debug("Azure response: $response");
        echo "❌ Face comparison failed.";
    }

    exit;
}
?>

<!-- ✅ Simple HTML UI -->
<h2>Step 1: Upload Initial Selfie</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="profile_photo" accept="image/*" required>
    <button type="submit" name="submit_profile">Save Profile Photo</button>
</form>

<hr>

<h2>Step 2: Upload Selfie for Match</h2>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="verify_photo" accept="image/*" required>
    <button type="submit" name="submit_verify">Run Match Test</button>
</form>
