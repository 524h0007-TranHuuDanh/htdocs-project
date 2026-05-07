<?php
// api/delete_image.php
// SỬA WARN: Thêm kiểm tra quyền sở hữu trước khi xóa ảnh
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID ảnh không hợp lệ.']);
    exit;
}

try {
    // SỬA: Kiểm tra ảnh này có thuộc về ghi chú của user hiện tại không
    $stmt = $pdo->prepare(
        "SELECT ni.id, ni.file_path
         FROM note_images ni
         JOIN notes n ON ni.note_id = n.id
         WHERE ni.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$id, $user_id]);
    $img = $stmt->fetch();

    if (!$img) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa ảnh này.']);
        exit;
    }

    // Xóa file vật lý
    $physical_path = '../' . $img['file_path'];
    if (file_exists($physical_path)) {
        unlink($physical_path);
    }

    // Xóa record trong database
    $stmt = $pdo->prepare("DELETE FROM note_images WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}