<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$ids = $_POST['ids'] ?? '';
if (!is_array($ids)) {
    $ids = explode(',', $ids);
}
$ids = array_filter(array_map('intval', $ids));
$user_id = $_SESSION['user_id'];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Không có ghi chú nào được chọn']);
    exit;
}

try {
    // Kiểm tra quyền sở hữu (chỉ chủ mới bulk xóa/khôi phục/share)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE id IN ($placeholders) AND user_id = ?");
    $checkStmt->execute(array_merge($ids, [$user_id]));
    if ($checkStmt->fetchColumn() != count($ids)) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền với một số ghi chú']);
        exit;
    }

    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã chuyển ' . count($ids) . ' ghi chú vào thùng rác']);
    } 
    elseif ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã khôi phục ' . count($ids) . ' ghi chú']);
    }
    elseif ($action === 'permanent') {
        // Xóa vĩnh viễn: cần xóa ảnh vật lý trước
        $imgStmt = $pdo->prepare("SELECT file_path FROM note_images WHERE note_id IN ($placeholders)");
        $imgStmt->execute($ids);
        while ($img = $imgStmt->fetch()) {
            $file = '../' . $img['file_path'];
            if (file_exists($file)) @unlink($file);
        }
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã xóa vĩnh viễn ' . count($ids) . ' ghi chú']);
    }
    elseif ($action === 'share') {
        // Nhận thêm email và permission
        $emails = $_POST['emails'] ?? '';
        $permission = $_POST['permission'] ?? 'read';
        if (empty($emails)) {
            echo json_encode(['success' => false, 'message' => 'Chưa nhập email']);
            exit;
        }
        $emailList = array_unique(array_map('trim', explode(',', $emails)));
        $successCount = 0;
        $messages = [];
        foreach ($ids as $note_id) {
            foreach ($emailList as $email) {
                // Kiểm tra user tồn tại
                $userStmt = $pdo->prepare("SELECT id, email, display_name FROM users WHERE email = ?");
                $userStmt->execute([$email]);
                $receiver = $userStmt->fetch();
                if (!$receiver || $receiver['id'] == $user_id) continue;
                // Upsert shared_notes
                $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
                $check->execute([$note_id, $email]);
                if ($check->fetch()) {
                    $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
                    $update->execute([$permission, $note_id, $email]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)");
                    $insert->execute([$note_id, $user_id, $email, $permission]);
                }
                $successCount++;
                // Gửi mail (có thể gửi riêng từng note, nhưng để tránh spam, gửi một mail tổng hợp? Tạm thời gửi cho mỗi lần share)
                require_once '../mail_config.php';
                $senderName = $_SESSION['display_name'] ?? 'Một người dùng';
                sendShareNotification($email, $receiver['display_name'], $senderName, "Nhiều ghi chú");
            }
        }
        echo json_encode(['success' => true, 'message' => "Đã chia sẻ thành công $successCount lượt"]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}