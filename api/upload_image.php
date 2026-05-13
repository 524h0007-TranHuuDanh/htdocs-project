<?php
// api/upload_image.php
// SỬA WARN: Dùng finfo_file() kiểm tra MIME server-side thay vì tin $_FILES['type']
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image']) || !isset($_POST['note_id'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

require_valid_csrf_post();

$note_id = intval($_POST['note_id']);
$user_id = $_SESSION['user_id'];
$file    = $_FILES['image'];

// Kiểm tra lỗi upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Lỗi upload file (code: ' . $file['error'] . ').']);
    exit;
}

// Kiểm tra note_id có thuộc về user này không (hoặc được share với quyền edit)
$meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$meStmt->execute([$user_id]);
$my_email = $meStmt->fetchColumn();

$noteCheck = $pdo->prepare(
    "SELECT id FROM notes WHERE id = ? AND user_id = ?
     UNION
     SELECT n.id FROM notes n
     JOIN shared_notes sn ON sn.note_id = n.id
     WHERE n.id = ? AND sn.recipient_email = ? AND sn.permission = 'edit'"
);
$noteCheck->execute([$note_id, $user_id, $note_id, $my_email]);
if (!$noteCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm ảnh vào ghi chú này.']);
    exit;
}

// Giới hạn kích thước file: 5MB
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File quá lớn. Tối đa 5MB.']);
    exit;
}

// SỬA: Kiểm tra MIME type phía server bằng finfo (không tin client)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ cho phép file ảnh (jpg, png, gif, webp).']);
    exit;
}

// Map MIME -> extension an toàn
$ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$extension = $ext_map[$mime_type];

// Tạo tên file duy nhất
$new_filename = uniqid('img_', true) . '.' . $extension;
$upload_dir   = '../uploads/';
$upload_path  = $upload_dir . $new_filename;
$db_path      = 'uploads/' . $new_filename;

// Tạo thư mục nếu chưa có
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $stmt = $pdo->prepare("INSERT INTO note_images (note_id, file_path) VALUES (?, ?)");
    $stmt->execute([$note_id, $db_path]);

    echo json_encode([
        'success'  => true,
        'file_path' => $db_path,
        'image_id' => $pdo->lastInsertId()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu file vào thư mục uploads.']);
}