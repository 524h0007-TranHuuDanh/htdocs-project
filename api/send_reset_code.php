<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$type = $_POST['type'] ?? 'otp';
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, display_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']);
    exit;
}

$reset_token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

if ($type === 'link') {
    $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
        ->execute([$reset_token, $expiry, $user_id]);
    $sent = sendResetLinkEmail($user['email'], $user['display_name'], $reset_token);
    $msg = 'Link đặt lại mật khẩu đã được gửi';
} else {
    $otp = rand(100000, 999999);
    // SỬA: thêm reset_token_expiry cho OTP (giống link)
    $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
        ->execute([$otp, $expiry, $user_id]);
    $sent = sendResetOTPEmail($user['email'], $user['display_name'], $otp);
    $msg = 'Mã OTP đã được gửi';
}

echo json_encode(['success' => $sent, 'message' => $sent ? $msg : 'Gửi email thất bại']);