<?php
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'note_management';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'root';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>