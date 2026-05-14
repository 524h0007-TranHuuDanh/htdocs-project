<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$new_name = trim($_POST['display_name'] ?? '');
if (strlen($new_name) < 3 || strlen($new_name) > 50) {
    echo json_encode(['success' => false, 'message' => 'Tên phải từ 3-50 ký tự']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
$stmt->execute([$new_name, $_SESSION['user_id']]);
$_SESSION['display_name'] = $new_name;
echo json_encode(['success' => true]);