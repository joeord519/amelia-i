<?php
require_once(__DIR__ . '/includes/db_connect.php');
$conn = getDB();

$results = $conn->query("SELECT tail_number FROM wp_aircraft ORDER BY tail_number ASC")->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($results);
