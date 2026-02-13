<?php
require_once('db_connect.php');

$query = "SELECT id, name, airport_code FROM wp_locations";
$stmt = $pdo->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($locations) {
    echo json_encode(['success' => true, 'locations' => $locations]);
} else {
    echo json_encode(['success' => false, 'message' => 'No locations found.']);
}
exit;
?>
