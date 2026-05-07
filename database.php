<?php
// Cấu hình Database
$host = 'localhost';
$db   = 'note_management';
$user = 'root'; // Thay đổi theo máy của bạn (thường là root trên XAMPP)
$pass = '';     // Thay đổi theo máy của bạn (thường để trống trên XAMPP)
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
     // Lưu ý: Trong môi trường production không nên hiển thị lỗi chi tiết thế này
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>