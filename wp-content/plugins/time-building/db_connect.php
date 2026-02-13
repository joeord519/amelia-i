<?php
// Database Credentials
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';
$username = 'uizsmtjki2wdx';
$password = '7w26g#@$>iD5';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}
?>