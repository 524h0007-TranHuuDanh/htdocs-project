<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$sender_id  = $_SESSION['user_id'];
$note_id    = intval($_POST['note_id'] ?? 0);
$share_with = trim($_POST['share_with'] ?? '');
$permission = $_POST['permission'] ?? 'read';

if ($note_id <= 0 || empty($share_with)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
    exit;
}

if (!in_array($permission, ['read', 'edit'])) {
    $permission = 'read';
}

try {
    // Kiểm tra ghi chú thuộc về owner
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $sender_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
        exit;
    }

    $emails = array_unique(array_map('trim', explode(',', $share_with)));
    $successCount = 0;
    $messages = [];

    foreach ($emails as $shareWith) {
        if (empty($shareWith)) continue;

        // Tìm người nhận theo email hoặc display_name
        $stmt = $pdo->prepare("SELECT id, email, display_name FROM users 
                              WHERE email = ? OR display_name = ? LIMIT 1");
        $stmt->execute([$shareWith, $shareWith]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receiver) {
            $messages[] = "Không tìm thấy: " . htmlspecialchars($shareWith);
            continue;
        }

        if ($receiver['id'] == $sender_id) {
            $messages[] = "Không thể chia sẻ cho chính mình";
            continue;
        }

        $recipient_email = $receiver['email'];

        // Kiểm tra đã chia sẻ chưa
        $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
        $check->execute([$note_id, $recipient_email]);

        if ($check->fetch()) {
            // Cập nhật quyền
            $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
            $update->execute([$permission, $note_id, $recipient_email]);
            $messages[] = "Đã cập nhật quyền cho " . htmlspecialchars($receiver['display_name']);
        } else {
            // Thêm mới
            $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) 
                                    VALUES (?, ?, ?, ?)");
            $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
            $messages[] = "Đã chia sẻ thành công cho " . htmlspecialchars($receiver['display_name']);
        }
        $successCount++;
    }

    $finalMessage = $successCount > 0 
        ? "Đã chia sẻ cho $successCount người.\n" . implode("\n", $messages)
        : "Không chia sẻ được cho ai.";

    echo json_encode([
        'success' => $successCount > 0,
        'message' => $finalMessage
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
?>