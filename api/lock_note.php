<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id      = intval($_POST['note_id'] ?? 0);
$action       = $_POST['action'] ?? ''; 
$password     = $_POST['password'] ?? '';           // new password
$confirm_pass = $_POST['confirm_password'] ?? '';   // confirm new
$old_password = $_POST['old_password'] ?? '';
$user_id      = $_SESSION['user_id'];

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    $current_hash = $note['password_hash'];

    // ====================== LOCK - ĐẶT MẬT KHẨU MỚI ======================
    if ($action === 'lock') {
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 4 ký tự!']);
            exit;
        }
        if ($password !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$hash, $note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã khóa ghi chú thành công']);

    // ====================== UNLOCK ======================
    } elseif ($action === 'unlock') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        $pdo->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?")
            ->execute([$note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã mở khóa ghi chú']);

    // ====================== CHANGE PASSWORD ======================
    } elseif ($action === 'change') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng!']);
            exit;
        }
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 4 ký tự!']);
            exit;
        }
        if ($password !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!']);
            exit;
        }
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$new_hash, $note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã đổi mật khẩu thành công']);

    // ====================== VERIFY ======================
    } elseif ($action === 'verify') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
    }

} catch (PDOException $e) {
    error_log("Lock Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
?>