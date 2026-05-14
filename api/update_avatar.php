<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Không có file ảnh']);
    exit;
}

$file = $_FILES['avatar'];
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$max_size = 2 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Ảnh không được quá 2MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận JPG, PNG, GIF, WEBP']);
    exit;
}

$ext_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];
$ext = $ext_map[$mime_type];
$new_filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
$target_path = $upload_dir . $new_filename;
$db_path = 'uploads/avatars/' . $new_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh']);
    exit;
}

// Xóa avatar cũ (nếu không phải default)
$old = $_SESSION['avatar'] ?? '';
if ($old && $old !== 'uploads/avatars/default-avatar.png' && file_exists(__DIR__ . '/../' . $old)) {
    @unlink(__DIR__ . '/../' . $old);
}

$stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->execute([$db_path, $_SESSION['user_id']]);
$_SESSION['avatar'] = $db_path;

echo json_encode(['success' => true, 'avatar' => $db_path]);