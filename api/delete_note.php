<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id       = intval($_POST['id'] ?? 0);
$action   = $_POST['action'] ?? 'trash';
$password = $_POST['delete_password'] ?? '';
$user_id  = $_SESSION['user_id'];

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú hoặc bạn không có quyền!']);
        exit;
    }

    if (!empty($note['password_hash'])) {
        if (empty($password)) {
            echo json_encode([
                'success' => false,
                'require_password' => true,
                'message' => 'Ghi chú này được bảo vệ bằng mật khẩu. Vui lòng nhập mật khẩu để xóa!'
            ]);
            exit;
        }
        if (!password_verify($password, $note['password_hash'])) {
            echo json_encode([
                'success' => false,
                'require_password' => true,
                'message' => 'Mật khẩu không đúng!'
            ]);
            exit;
        }
    }

    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Đã chuyển vào thùng rác']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Đã xóa vĩnh viễn']);
    }

} catch (Exception $e) {
    error_log("Delete Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại!']);
}
?>