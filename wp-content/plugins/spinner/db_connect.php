<?php
// /wp-content/plugins/spinner/db_connect.php
$host = 'localhost';
$dbname = 'dbqn6ggmq2vlto';   // <-- your DB
$username = 'uizsmtjki2wdx';  // <-- your user
$password = '7w26g#@$>iD5';   // <-- your pass
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, $username, $password, $options);

