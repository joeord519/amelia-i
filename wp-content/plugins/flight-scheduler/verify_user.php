<?php
require_once('db_connect.php');

$mobile = $_POST['mobile'];
$securityWord = $_POST['securityWord'];

$query = $pdo->prepare("SELECT * FROM wp_students WHERE phone = ? AND security_word = ?");
$query->execute([$mobile, $securityWord]);
$user = $query->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
}
exit;
?>
