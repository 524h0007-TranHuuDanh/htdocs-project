<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? 'trash'; // 'trash' hoặc 'permanent'

try {
    if ($action === 'trash') {
        // Chuyển vào thùng rác
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
    } else {
        // Xóa vĩnh viễn (bao gồm cả ảnh, nhãn liên quan thông qua CASCADE nếu db đã set, hoặc xóa cứng)
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    }
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>