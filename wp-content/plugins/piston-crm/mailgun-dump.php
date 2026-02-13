<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

file_put_contents(__DIR__ . '/dump.log', print_r($_POST, true) . "\nFILES:\n" . print_r($_FILES, true) . "\n", FILE_APPEND);
echo 'Logged';
