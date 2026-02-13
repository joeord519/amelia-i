<?php
require_once(__DIR__ . '/db_connect.php');

$airport = trim($_GET['home_airport'] ?? '');
if (!$airport) exit(json_encode([]));

$db = getDB();
$stmt = $db->prepare("SELECT tail_number FROM wp_aircraft WHERE home_airport = :a ORDER BY tail_number ASC");
$stmt->execute([':a' => $airport]);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($rows);
