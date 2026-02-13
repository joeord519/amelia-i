<?php
// /wp-content/plugins/pistonpay/db_connect.php

function getDB() {
  $host = 'localhost';
  $dbname = 'dbqn6ggmq2vlto'; // ✅ your actual DB name
  $username = 'uizsmtjki2wdx'; // ✅ your DB username
  $password = '7w26g#@$>iD5'; // ✅ your DB password
  $charset = 'utf8mb4';

  $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
  $options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ];

  try {
    return new PDO($dsn, $username, $password, $options);
  } catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int)$e->getCode());
  }
}
