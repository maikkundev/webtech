<?php
// Prevent direct access to this file
defined('SECURE_ACCESS') or define('SECURE_ACCESS', true);
if (!defined('SECURE_ACCESS')) {
  die('Direct access to this file is not allowed.');
}

// throw exceptions on mysqli errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// make sure .env exists
$envFile = __DIR__ . '/../.env';
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

// Create users table
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

// Create playlists table
$sql = "
CREATE TABLE IF NOT EXISTS `playlists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($db->query($sql) === FALSE) {
  die("Playlists table creation failed: " . $db->error);
}

// Create videos table
$sql = "
CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `playlist_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `youtube_id` VARCHAR(20) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `thumbnail_url` VARCHAR(255),
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`playlist_id`) REFERENCES `playlists`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($db->query($sql) === FALSE) {
  die("Videos table creation failed: " . $db->error);
}
