<?php
// api/get_note_labels.php
// SỬA WARN: Kiểm tra note thuộc về user hoặc được chia sẻ trước khi trả nhãn
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
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Kiểm tra quyền truy cập vào note trước
    $accessCheck = $pdo->prepare(
        "SELECT id FROM notes WHERE id = ? AND user_id = ?
         UNION
         SELECT n.id FROM notes n
         JOIN shared_notes sn ON sn.note_id = n.id
         WHERE n.id = ? AND sn.recipient_email = ?"
    );
    $accessCheck->execute([$note_id, $user_id, $note_id, $my_email]);

    if (!$accessCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT l.* FROM labels l
         JOIN note_labels nl ON l.id = nl.label_id
         WHERE nl.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}