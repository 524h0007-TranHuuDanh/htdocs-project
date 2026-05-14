<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$old = $_POST['old_password'] ?? '';
$new = $_POST['new_password'] ?? '';

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải ≥6 ký tự']);
    exit;
}

$stmt = $pdo->prepare("SELECT password_hash, email, display_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!password_verify($old, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng']);
    exit;
}

$new_hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$new_hash, $_SESSION['user_id']]);

// Gửi email thông báo
sendPasswordChangedNotification($user['email'], $user['display_name']);

echo json_encode(['success' => true]);