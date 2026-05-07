<?php
// api/save_note.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + kiểm tra thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$id      = trim($_POST['id']      ?? '');
$title   = $_POST['title']        ?? '';
$content = $_POST['content']      ?? '';

try {
    if (empty($id)) {
        // Tạo ghi chú mới
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $title, $content]);
        echo json_encode(['success' => true, 'note_id' => $pdo->lastInsertId()]);
    } else {
        $id = intval($id);

        // Kiểm tra quyền: chủ sở hữu HOẶC được share với quyền edit
        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$user_id]);
        $my_email = $meStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT n.user_id,
                   (SELECT permission FROM shared_notes
                    WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
            FROM notes n
            WHERE n.id = ?
        ");
        $stmt->execute([$my_email, $id]);
        $note = $stmt->fetch();

        if ($note && ($note['user_id'] == $user_id || $note['permission'] === 'edit')) {
            $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
            echo json_encode(['success' => true, 'note_id' => $id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Bạn không có quyền chỉnh sửa ghi chú này!']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}