<?php
function getDB() {
  $host = 'localhost';
  $dbname = 'dbqn6ggmq2vlto';
  $username = 'uizsmtjki2wdx';
  $password = '7w26g#@$>iD5';
  $charset = 'utf8mb4';

  $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
  $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ];

  try {
    return new PDO($dsn, $username, $password, $options);
  } catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
  }
}
