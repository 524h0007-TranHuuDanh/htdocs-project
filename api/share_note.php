<?php
// api/share_note.php
// SỬA WARN: Tìm người nhận theo cả email lẫn display_name (khớp với UI)
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$sender_id  = $_SESSION['user_id'];
$note_id    = intval($_POST['note_id']    ?? 0);
$share_with = trim($_POST['share_with']   ?? '');
$permission = $_POST['permission']        ?? 'read';

if (empty($share_with)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập Email hoặc Tên người nhận!']);
    exit;
}

if (!in_array($permission, ['read', 'edit'])) {
    $permission = 'read';
}

try {
    // SỬA: Tìm người nhận theo email HOẶC display_name (case-insensitive)
    $stmt = $pdo->prepare(
        "SELECT id, email, display_name FROM users
         WHERE email = ? OR display_name = ?
         LIMIT 1"
    );
    $stmt->execute([$share_with, $share_with]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receiver) {
        echo json_encode([
            'success' => false,
            'message' => 'Không tìm thấy người dùng với email/tên: "' . htmlspecialchars($share_with) . '"'
        ]);
        exit;
    }

    if ($receiver['id'] == $sender_id) {
        echo json_encode(['success' => false, 'message' => 'Bạn không thể tự chia sẻ cho chính mình!']);
        exit;
    }

    // Kiểm tra ghi chú thuộc về người gửi
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $sender_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
        exit;
    }

    // Dùng email thực của người nhận (trường hợp user nhập display_name)
    $recipient_email = $receiver['email'];

    // Kiểm tra đã chia sẻ chưa
    $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
    $check->execute([$note_id, $recipient_email]);

    if ($check->fetch()) {
        // Cập nhật permission nếu đã tồn tại
        $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
        $update->execute([$permission, $note_id, $recipient_email]);
        echo json_encode([
            'success' => true,
            'message' => 'Đã cập nhật quyền cho ' . htmlspecialchars($receiver['display_name']) . '.'
        ]);
    } else {
        $insert = $pdo->prepare(
            "INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)"
        );
        $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
        echo json_encode([
            'success' => true,
            'message' => 'Đã chia sẻ thành công cho ' . htmlspecialchars($receiver['display_name']) . '!'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
}