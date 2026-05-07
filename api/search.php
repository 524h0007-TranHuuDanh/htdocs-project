<?php
// api/search.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id'];
$keyword   = $_GET['q']        ?? '';
$label_id  = $_GET['label_id'] ?? null;
$view_mode = $_GET['view']     ?? 'my_notes';

$params     = [];
$searchTerm = "%$keyword%";

try {
    if ($view_mode === 'shared') {
        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$user_id]);
        $my_email = $meStmt->fetchColumn();

        $sql = "SELECT n.*, sn.permission, u.display_name AS owner_name
                FROM shared_notes sn
                JOIN notes n  ON sn.note_id  = n.id
                JOIN users u  ON n.user_id   = u.id
                WHERE sn.recipient_email = ?
                  AND n.user_id != ?
                  AND n.is_trashed = 0";
        $params[] = $my_email;
        $params[] = $user_id;

    } elseif ($view_mode === 'trash') {
        $sql      = "SELECT n.* FROM notes n WHERE n.user_id = ? AND n.is_trashed = 1";
        $params[] = $user_id;

    } else {
        // my_notes
        $sql      = "SELECT n.* FROM notes n WHERE n.user_id = ? AND n.is_trashed = 0";
        $params[] = $user_id;
        if ($label_id && $label_id !== 'null') {
            $sql     .= " AND n.id IN (SELECT note_id FROM note_labels WHERE label_id = ?)";
            $params[] = intval($label_id);
        }
    }

    $sql     .= " AND (n.title LIKE ? OR n.content LIKE ?) ORDER BY n.is_pinned DESC, n.updated_at DESC";
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ẩn nội dung ghi chú bị khóa
    foreach ($notes as &$note) {
        if (!empty($note['password_hash'])) {
            $note['is_locked'] = 1;
            $note['title']     = '🔒 Ghi chú bí mật';
            $note['content']   = 'Nhập mật khẩu để xem...';
        } else {
            $note['is_locked'] = 0;
        }
        unset($note['password_hash']);
    }

    echo json_encode($notes);

} catch (PDOException $e) {
    echo json_encode([]);
}