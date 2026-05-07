<?php
// api/get_note_images.php
// SỬA CRIT 2: Cho phép người được chia sẻ xem ảnh của ghi chú
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id = intval($_GET['note_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Lấy email của user hiện tại để kiểm tra shared_notes
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Truy vấn kiểm tra cả chủ sở hữu VÀ người được chia sẻ
    $sql = "SELECT ni.id, ni.file_path
            FROM note_images ni
            JOIN notes n ON ni.note_id = n.id
            WHERE ni.note_id = ?
              AND (
                n.user_id = ?
                OR EXISTS (
                    SELECT 1 FROM shared_notes
                    WHERE note_id = n.id
                      AND recipient_email = ?
                )
              )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$note_id, $user_id, $my_email]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($images);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}