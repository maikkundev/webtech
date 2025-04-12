<?php
echo '<h1>Hello Docker!</h1>';

// Database connection test
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');

try {
  $conn = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
  echo "Connected to MySQL successfully!";
} catch (PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
