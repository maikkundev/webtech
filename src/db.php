<?php

// throw exceptions on mysqli errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// make sure .env exists
$envFile = __DIR__ . '/.env';
if (! file_exists($envFile)) {
  die("Env file missing: {$envFile}");
}

// parse with typed values
$env = parse_ini_file($envFile, INI_SCANNER_TYPED);
if ($env === false) {
  die("Failed to parse .env");
}

$dbHost = $env['MYSQL_HOST']     ?? getenv('DB_HOST') ?? 'mysql';
$dbPort = $env['MYSQL_PORT']     ?? 3306;
$dbName = $env['MYSQL_DATABASE'] ?? 'phpapp';
$dbUser = $env['MYSQL_USER']     ?? 'user';
$dbPass = $env['MYSQL_PASSWORD'] ?? 'mypassword';

try {
  $db = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
} catch (mysqli_sql_exception $e) {
  die("Connection failed: " . $e->getMessage());
}

$sql = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `firstname` VARCHAR(50) NOT NULL,
  `lastname` VARCHAR(50) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($db->query($sql) === FALSE) {
  die("Table creation failed: " . $db->error);
}
