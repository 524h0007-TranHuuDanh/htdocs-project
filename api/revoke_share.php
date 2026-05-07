<?php
// api/revoke_share.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$share_id = intval($_POST['share_id'] ?? 0);

try {
    // Chỉ chủ ghi chú mới được thu hồi quyền
    $stmt = $pdo->prepare(
        "DELETE sn FROM shared_notes sn
         JOIN notes n ON sn.note_id = n.id
         WHERE sn.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$share_id, $user_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}