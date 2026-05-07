<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';
$is_pinned = $_POST['is_pinned'] ?? 0;

if (empty($id)) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $pinned_at = $is_pinned ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE notes SET is_pinned = ?, pinned_at = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$is_pinned, $pinned_at, $id, $user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>