<?php

$env = parse_ini_file(__DIR__ . '/.env');

// Database connection settings from the .env file
$dbHost = $env['MYSQL_HOST'] ?? 'localhost';
$dbName = $env['MYSQL_DATABASE'] ?? 'phpapp';
$dbUser = $env['MYSQL_USER'] ?? 'user';
$dbPass = $env['MYSQL_PASSWORD'] ?? 'mypassword';

$db = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($db->connect_error) {
  die("Connection failed: " . $db->connect_error);
}
