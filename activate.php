<?php
require_once 'database.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("UPDATE users SET is_activated = 1, activation_token = NULL WHERE activation_token = ?");
    $stmt->execute([$token]);

    if ($stmt->rowCount() > 0) {
        echo "Tài khoản đã được kích hoạt! <a href='login.php'>Đăng nhập ngay</a>";
    } else {
        echo "Token không hợp lệ hoặc tài khoản đã kích hoạt.";
    }
}
?>