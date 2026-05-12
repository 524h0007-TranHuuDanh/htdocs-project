<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id       = intval($_POST['id']       ?? 0);
$action   = $_POST['action']          ?? 'trash'; // 'trash' hoặc 'permanent'
$password = $_POST['delete_password'] ?? '';      // mật khẩu xác nhận xóa (nếu note bị khóa)
$user_id  = $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID không hợp lệ']);
    exit;
}

try {
    // Lấy thông tin note: kiểm tra chủ sở hữu và trạng thái khóa
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy ghi chú hoặc bạn không có quyền!']);
        exit;
    }

    // Nếu note có mật khẩu → bắt buộc phải xác thực trước khi xóa
    if (!empty($note['password_hash'])) {
        if (empty($password)) {
            echo json_encode([
                'success'       => false,
                'require_password' => true,
                'message'       => 'Ghi chú này được bảo vệ bằng mật khẩu. Vui lòng nhập mật khẩu để xác nhận xóa!'
            ]);
            exit;
        }

        if (!password_verify($password, $note['password_hash'])) {
            echo json_encode([
                'success'       => false,
                'require_password' => true,
                'message'       => 'Mật khẩu không đúng!'
            ]);
            exit;
        }
    }

    // Thực hiện xóa
    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    }

    $stmt->execute([$id, $user_id]);
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>