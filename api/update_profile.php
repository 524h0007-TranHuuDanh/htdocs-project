<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$font_size = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$avatar_path = $_SESSION['avatar'] ?? 'default-avatar.png';

try {
    // 1. Xử lý upload Avatar nếu có file gửi lên
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            // Tạo thư mục nếu chưa có
            $upload_dir = '../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_filename)) {
                $avatar_path = 'uploads/avatars/' . $new_filename;
            }
        }
    }

    // 2. Cập nhật vào Database
    // Lưu ý: Đảm bảo bảng users của bạn đã có đủ các cột: avatar, font_size, theme_color
    $stmt = $pdo->prepare("UPDATE users SET avatar = ?, font_size = ?, theme_color = ? WHERE id = ?");
    $stmt->execute([$avatar_path, $font_size, $theme_color, $user_id]);

    // 3. Cập nhật lại Session để giao diện thay đổi ngay lập tức
    $_SESSION['avatar'] = $avatar_path;
    $_SESSION['font_size'] = $font_size;
    $_SESSION['theme_color'] = $theme_color;

    echo json_encode([
        'success' => true, 
        'message' => 'Cập nhật thành công',
        'avatar' => $avatar_path
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Lỗi Database: ' . $e->getMessage()
    ]);
}