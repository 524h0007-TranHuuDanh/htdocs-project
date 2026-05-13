<?php
// api/verify_note.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$note_id  = intval($_POST['note_id']  ?? 0);
$password = $_POST['password'] ?? '';

try {
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // Tìm ghi chú: chủ sở hữu HOẶC được share qua email
    $stmt = $pdo->prepare("
        SELECT n.title, n.content, n.password_hash, n.color, n.user_id,
               (SELECT permission FROM shared_notes
                WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
        FROM notes n
        WHERE n.id = ?
          AND (
            n.user_id = ?
            OR EXISTS (SELECT 1 FROM shared_notes WHERE note_id = n.id AND recipient_email = ?)
          )
    ");
    $stmt->execute([$my_email, $note_id, $user_id, $my_email]);
    $note = $stmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    if (!password_verify($password, $note['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
        exit;
    }

    $perm = ($note['user_id'] == $user_id) ? 'owner' : $note['permission'];
    echo json_encode([
        'success'    => true,
        'title'      => $note['title'],
        'content'    => $note['content'],
        'color'      => $note['color'],
        'permission' => $perm
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}