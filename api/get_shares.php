<?php
// api/get_shares.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$note_id = intval($_GET['note_id'] ?? 0);

try {
    // Kiểm tra note thuộc về user hiện tại (chỉ chủ mới xem được danh sách share)
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $user_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sn.id              AS share_id,
            sn.recipient_email AS email,
            COALESCE(u.display_name, sn.recipient_email) AS display_name,
            sn.permission
         FROM shared_notes sn
         LEFT JOIN users u ON u.email = sn.recipient_email
         WHERE sn.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}