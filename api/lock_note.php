<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id      = intval($_POST['note_id']      ?? 0);
$action       = $_POST['action']              ?? 'lock'; // 'lock' | 'unlock' | 'change'
$password     = $_POST['password']            ?? '';     // mật khẩu mới (lock / change)
$old_password = $_POST['old_password']        ?? '';     // mật khẩu cũ (unlock / change)
$user_id      = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    // Lấy password_hash hiện tại của note
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    $current_hash = $note['password_hash'];

    // ── ĐẶT KHÓA MỚI ────────────────────────────────────────────────────────
    if ($action === 'lock') {
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 4 ký tự!']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$hash, $note_id, $user_id]);
        echo json_encode(['success' => true]);

    // ── GỠ KHÓA ─────────────────────────────────────────────────────────────
    } elseif ($action === 'unlock') {
        // Bắt buộc xác thực mật khẩu cũ
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
        echo json_encode(['success' => true]);

    // ── ĐỔI MẬT KHẨU ────────────────────────────────────────────────────────
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
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$new_hash, $note_id, $user_id]);
        echo json_encode(['success' => true]);

    // ── XÁC THỰC MẬT KHẨU CŨ (dùng trước bước đổi mật khẩu) ──────────────────
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>