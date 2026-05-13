<?php
/**
 * API: Cập nhật thông tin profile người dùng
 * Đã cải tiến: CSRF, Security, Validation, Error Handling
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

// ====================== SECURITY CHECK ======================
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode([
        'success' => false,
        'message' => 'Yêu cầu không hợp lệ (CSRF)'
    ]);
    exit;
}

// ====================== DEFAULT VALUES ======================
$default_avatar = 'uploads/avatars/default-avatar.png';

// ====================== INPUT VALIDATION ======================
$font_size   = $_POST['font_size']   ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$note_color  = $_POST['note_color']  ?? '#ffffff';

$allowed_fonts  = ['14px', '16px', '18px'];
$allowed_themes = ['light', 'dark'];

if (!in_array($font_size, $allowed_fonts)) {
    $font_size = '16px';
}

if (!in_array($theme_color, $allowed_themes)) {
    $theme_color = 'light';
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $note_color)) {
    $note_color = '#ffffff';
}

// ====================== CURRENT AVATAR ======================
$avatar_path = !empty($_SESSION['avatar']) 
    ? $_SESSION['avatar'] 
    : $default_avatar;

try {
    // ====================== UPLOAD AVATAR ======================
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $upload_dir = __DIR__ . '/../uploads/avatars/';

        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Kiểm tra kích thước (tối đa 2MB)
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception('Ảnh đại diện không được vượt quá 2MB');
        }

        // Kiểm tra MIME type an toàn
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes)) {
            throw new Exception('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
        }

        // Tạo tên file an toàn
        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $ext_map[$mime_type];
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $target_path = $upload_dir . $new_filename;
        $db_path = 'uploads/avatars/' . $new_filename;

        // Upload file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new Exception('Không thể tải lên ảnh đại diện');
        }

        // Xóa avatar cũ nếu không phải avatar mặc định
        if (!empty($_SESSION['avatar']) && $_SESSION['avatar'] !== $default_avatar) {
            $old_file = __DIR__ . '/../' . $_SESSION['avatar'];
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }

        $avatar_path = $db_path;
    }

    // ====================== UPDATE DATABASE ======================
    $stmt = $pdo->prepare("
        UPDATE users 
        SET font_size = ?, 
            theme_color = ?, 
            note_color = ?, 
            avatar = ? 
        WHERE id = ?
    ");

    $stmt->execute([
        $font_size,
        $theme_color,
        $note_color,
        $avatar_path,
        $user_id
    ]);

    // ====================== UPDATE SESSION ======================
    $_SESSION['font_size']   = $font_size;
    $_SESSION['theme_color'] = $theme_color;
    $_SESSION['note_color']  = $note_color;
    $_SESSION['avatar']      = $avatar_path;

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thông tin thành công',
        'avatar'  => $avatar_path
    ]);

} catch (Exception $e) {
    error_log("Update Profile Error (User ID: $user_id): " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Lỗi hệ thống. Vui lòng thử lại sau.'
    ]);
}