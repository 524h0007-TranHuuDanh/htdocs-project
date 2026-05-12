<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$id      = intval($_POST['id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$client_version = intval($_POST['version'] ?? 0);

try {
    if ($id <= 0) {
        // Tạo mới
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, version) VALUES (?, ?, ?, 1)");
        $stmt->execute([$user_id, $title, $content]);
        $new_id = $pdo->lastInsertId();

        broadcastNoteUpdate($new_id, $title, $content, $_SESSION['display_name'] ?? 'Người dùng');
        echo json_encode(['success' => true, 'note_id' => $new_id, 'version' => 1]);
        exit;
    }

    // Kiểm tra quyền
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT n.version, n.user_id,
               (SELECT permission FROM shared_notes WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
        FROM notes n WHERE n.id = ?
    ");
    $stmt->execute([$my_email, $id]);
    $note = $stmt->fetch();

    if (!$note || !($note['user_id'] == $user_id || $note['permission'] === 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Không có quyền chỉnh sửa']);
        exit;
    }

    // === SOFT CONFLICT CHECK (Chỉ conflict khi chênh lệch lớn) ===
    if ($client_version > 0 && $client_version < $note['version'] - 3) {
        echo json_encode([
            'success' => false,
            'conflict' => true,
            'message' => 'Có xung đột phiên bản. Đang tải lại ghi chú...'
        ]);
        exit;
    }

    $new_version = $note['version'] + 1;

    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, version = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $content, $new_version, $id]);

    broadcastNoteUpdate($id, $title, $content, $_SESSION['display_name'] ?? 'Người dùng');

    echo json_encode([
        'success' => true,
        'note_id' => $id,
        'version' => $new_version
    ]);

} catch (Exception $e) {
    error_log('Save Note Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
}

function broadcastNoteUpdate($note_id, $title, $content, $user_name) {
    // WebSocket sẽ xử lý
}
?>