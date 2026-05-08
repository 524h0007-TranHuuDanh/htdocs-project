<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

// =========================
// CHECK LOGIN
// =========================
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

// =========================
// DEFAULT AVATAR
// =========================
$default_avatar = 'uploads/avatars/default-avatar.png';

// =========================
// INPUT
// =========================
$font_size   = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$note_color  = $_POST['note_color'] ?? '#ffffff';

// =========================
// VALIDATE
// =========================
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

try {

    // =========================
    // CURRENT AVATAR
    // =========================
    $avatar_path = !empty($_SESSION['avatar'])
        ? $_SESSION['avatar']
        : $default_avatar;

    // =========================
    // UPLOAD NEW AVATAR
    // =========================
    if (
        isset($_FILES['avatar']) &&
        $_FILES['avatar']['error'] === UPLOAD_ERR_OK
    ) {

        $upload_dir = __DIR__ . '/../uploads/avatars/';

        // Tạo folder nếu chưa có
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Extension
        $ext = strtolower(
            pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION)
        );

        // Cho phép
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed_ext)) {
            throw new Exception('File ảnh không hợp lệ');
        }

        // Tên file mới
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;

        $target = $upload_dir . $new_name;

        // Upload
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
            throw new Exception('Upload avatar thất bại');
        }

        // =========================
        // DELETE OLD AVATAR
        // =========================
        if (
            !empty($_SESSION['avatar']) &&
            $_SESSION['avatar'] !== $default_avatar
        ) {

            $old_file = __DIR__ . '/../' . $_SESSION['avatar'];

            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Avatar mới
        $avatar_path = 'uploads/avatars/' . $new_name;
    }

    // =========================
    // UPDATE DB
    // =========================
    $stmt = $pdo->prepare("
        UPDATE users
        SET
            font_size = ?,
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

    // =========================
    // UPDATE SESSION
    // =========================
    $_SESSION['font_size']   = $font_size;
    $_SESSION['theme_color'] = $theme_color;
    $_SESSION['note_color']  = $note_color;
    $_SESSION['avatar']      = $avatar_path;

    // =========================
    // SUCCESS
    // =========================
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thành công',
        'avatar' => $avatar_path
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}