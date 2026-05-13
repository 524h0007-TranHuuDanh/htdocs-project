<?php
session_start();
require_once 'database.php';

$message = '';
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT id, display_name 
        FROM users 
        WHERE activation_token = ? 
          AND activation_expiry > NOW() 
          AND is_activated = 0 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("
            UPDATE users 
            SET is_activated = 1, 
                activation_token = NULL, 
                activation_expiry = NULL,
                email_verified_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$user['id']]);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['is_activated'] = 1;

        session_regenerate_id(true);

        $message = "Kích hoạt tài khoản thành công!";
        header("Location: index.php?activated=1");
        exit;
    } else {
        $message = "Link kích hoạt không hợp lệ hoặc đã hết hạn!";
    }
} else {
    $message = "Token không hợp lệ!";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kích hoạt tài khoản</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h3>Kích hoạt tài khoản</h3>
                    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                    <a href="login.php" class="btn btn-primary">Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>