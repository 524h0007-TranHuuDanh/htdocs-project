<?php
require_once 'auth_helper.php';
require_once '../database.php';

check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

$id      = intval($_POST['id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';

$client_version = intval($_POST['version'] ?? 0);

try {

    // =========================================================
    // CREATE NEW NOTE
    // =========================================================
    if ($id <= 0) {

        if (empty($title) && empty($content)) {

            echo json_encode([
                'success' => false,
                'message' => 'Vui lòng nhập tiêu đề hoặc nội dung ghi chú'
            ]);

            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO notes (
                user_id,
                title,
                content,
                version
            )
            VALUES (?, ?, ?, 1)
        ");

        $stmt->execute([
            $user_id,
            $title,
            $content
        ]);

        $new_id = $pdo->lastInsertId();

        broadcastNoteUpdate(
            $new_id,
            $title,
            $content,
            1,
            $_SESSION['display_name'] ?? 'Người dùng'
        );

        echo json_encode([
            'success' => true,
            'note_id' => $new_id,
            'version' => 1,
            'message' => 'Ghi chú mới đã được tạo'
        ]);

        exit;
    }

    // =========================================================
    // CHECK PERMISSION
    // =========================================================
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);

    $my_email = $meStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            n.version,
            n.title,
            n.content,
            n.user_id,
            (
                SELECT permission
                FROM shared_notes
                WHERE note_id = n.id
                  AND recipient_email = ?
                LIMIT 1
            ) AS permission
        FROM notes n
        WHERE n.id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $my_email,
        $id
    ]);

    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$note ||
        !(
            $note['user_id'] == $user_id ||
            $note['permission'] === 'edit'
        )
    ) {

        echo json_encode([
            'success' => false,
            'message' => 'Không có quyền chỉnh sửa ghi chú này'
        ]);

        exit;
    }

    // =========================================================
    // VERSION CHECK + ATOMIC UPDATE (optimistic locking)
    // Client must match current server version; UPDATE only if row still at that version.
    // =========================================================
    $serverVersion = (int) $note['version'];

    if ((int) $client_version !== $serverVersion) {

        echo json_encode([
            'success'          => false,
            'conflict'         => true,
            'version'          => $serverVersion,
            'latest_title'     => $note['title'] ?? '',
            'latest_content'   => $note['content'] ?? '',
            'message'          => 'Có xung đột phiên bản. Đang tải lại ghi chú...'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE notes
        SET
            title = ?,
            content = ?,
            version = version + 1,
            updated_at = NOW()
        WHERE id = ?
          AND version = ?
    ");

    $stmt->execute([
        $title,
        $content,
        $id,
        $serverVersion
    ]);

    if ($stmt->rowCount() === 0) {

        $stmtFresh = $pdo->prepare("
            SELECT version, title, content
            FROM notes
            WHERE id = ?
            LIMIT 1
        ");
        $stmtFresh->execute([$id]);
        $fresh = $stmtFresh->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success'          => false,
            'conflict'         => true,
            'version'          => (int) ($fresh['version'] ?? $serverVersion),
            'latest_title'     => $fresh['title'] ?? '',
            'latest_content'   => $fresh['content'] ?? '',
            'message'          => 'Ghi chú đã được cập nhật bởi người khác. Đang đồng bộ...'
        ]);

        exit;
    }

    $new_version = $serverVersion + 1;

    broadcastNoteUpdate(
        $id,
        $title,
        $content,
        $new_version,
        $_SESSION['display_name'] ?? 'Người dùng'
    );

    echo json_encode([
        'success' => true,
        'note_id' => $id,
        'version' => $new_version,
        'message' => 'Đã lưu thay đổi'
    ]);

} catch (Exception $e) {

    error_log('Save Note Error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống'
    ]);
}

// =========================================================
// BROADCAST REALTIME
// =========================================================
function broadcastNoteUpdate(
    $note_id,
    $title,
    $content,
    $version,
    $user_name
) {
    global $pdo;

    // Ratchet relays typing with DB version per message; verify row matches expected version after save.
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        return;
    }

    $id       = (int) $note_id;
    $expected = (int) $version;

    if ($id <= 0 || $expected < 1) {
        return;
    }

    $stmt = $pdo->prepare('SELECT version FROM notes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $actual = (int) $stmt->fetchColumn();

    if ($actual !== $expected) {
        error_log("broadcastNoteUpdate: version mismatch note={$id} expected={$expected} db={$actual}");
    }
}
?>