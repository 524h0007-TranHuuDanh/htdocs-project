<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();

header('Content-Type: application/json');

$sender_id  = $_SESSION['user_id'];
$note_id    = intval($_POST['note_id'] ?? 0);
$share_with = trim($_POST['share_with'] ?? '');
$permission = $_POST['permission'] ?? 'read';

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($note_id <= 0 || empty($share_with)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
    exit;
}

if (!in_array($permission, ['read', 'edit'])) $permission = 'read';

try {
    // Kiểm tra owner
    $noteCheck = $pdo->prepare("SELECT id, title FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $sender_id]);
    $note = $noteCheck->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
        exit;
    }

    $emails = array_unique(array_map('trim', explode(',', $share_with)));
    $successCount = 0;
    $messages = [];

    $senderName = $_SESSION['display_name'] ?? 'Một người dùng';

    foreach ($emails as $email) {
        if (empty($email)) continue;

        $stmt = $pdo->prepare("SELECT id, email, display_name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $receiver = $stmt->fetch();

        if (!$receiver || $receiver['id'] == $sender_id) {
            $messages[] = "Không hợp lệ: " . htmlspecialchars($email);
            continue;
        }

        $recipient_email = $receiver['email'];

        // Kiểm tra đã share chưa
        $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
        $check->execute([$note_id, $recipient_email]);

        if ($check->fetch()) {
            $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
            $update->execute([$permission, $note_id, $recipient_email]);
            $messages[] = "Đã cập nhật quyền cho " . htmlspecialchars($receiver['display_name']);
        } else {
            $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)");
            $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
            
            // Gửi email thông báo
            sendShareNotification($recipient_email, $receiver['display_name'], $senderName, $note['title']);
            
            $messages[] = "Đã chia sẻ thành công cho " . htmlspecialchars($receiver['display_name']);
        }
        $successCount++;
    }

    echo json_encode([
        'success' => $successCount > 0,
        'message' => $successCount > 0 
            ? "Đã chia sẻ cho $successCount người.\n" . implode("\n", $messages)
            : "Không chia sẻ được cho ai."
    ]);

} catch (PDOException $e) {
    error_log("Share Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi database']);
}
?>