<?php
// api/search.php

require_once 'auth_helper.php';
require_once '../database.php';

check_login();

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id'];
$keyword   = $_GET['q'] ?? '';
$label_id  = $_GET['label_id'] ?? null;
$view_mode = $_GET['view'] ?? 'my_notes';

$params = [];
$searchTerm = "%$keyword%";

try {

    // ─────────────────────────────────────────────
    // SHARED NOTES
    // ─────────────────────────────────────────────
    if ($view_mode === 'shared') {

        $meStmt = $pdo->prepare("
            SELECT email
            FROM users
            WHERE id = ?
        ");

        $meStmt->execute([$user_id]);

        $my_email = $meStmt->fetchColumn();

        $sql = "
            SELECT
                n.*,
                sn.permission,
                u.display_name AS owner_name,
                sn.shared_at
            FROM shared_notes sn
            JOIN notes n
                ON sn.note_id = n.id
            JOIN users u
                ON n.user_id = u.id
            WHERE sn.recipient_email = ?
              AND n.user_id != ?
              AND n.is_trashed = 0
        ";

        $params[] = $my_email;
        $params[] = $user_id;

    }

    // ─────────────────────────────────────────────
    // TRASH
    // ─────────────────────────────────────────────
    elseif ($view_mode === 'trash') {

        $sql = "
            SELECT n.*
            FROM notes n
            WHERE n.user_id = ?
              AND n.is_trashed = 1
        ";

        $params[] = $user_id;

    }

    // ─────────────────────────────────────────────
    // MY NOTES
    // ─────────────────────────────────────────────
    else {

        $sql = "
            SELECT n.*
            FROM notes n
            WHERE n.user_id = ?
              AND n.is_trashed = 0
        ";

        $params[] = $user_id;

        if ($label_id && $label_id !== 'null') {

            $sql .= "
                AND n.id IN (
                    SELECT note_id
                    FROM note_labels
                    WHERE label_id = ?
                )
            ";

            $params[] = intval($label_id);
        }
    }

    // ─────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────
    $sql .= "
        AND (
            n.title LIKE ?
            OR n.content LIKE ?
        )
    ";

    $params[] = $searchTerm;
    $params[] = $searchTerm;

    // ─────────────────────────────────────────────
    // ORDER
    // ─────────────────────────────────────────────
    if ($view_mode === 'shared') {

        $sql .= "
            ORDER BY
                sn.shared_at DESC,
                n.updated_at DESC
        ";

    } else {

        $sql .= "
            ORDER BY
                n.is_pinned DESC,
                n.pinned_at DESC,
                n.updated_at DESC
        ";
    }

    // ─────────────────────────────────────────────
    // EXECUTE
    // ─────────────────────────────────────────────
    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────
    // HIDE LOCKED CONTENT
    // ─────────────────────────────────────────────
    foreach ($notes as &$note) {

        if (!empty($note['password_hash'])) {

            $note['is_locked'] = 1;
            $note['title'] = '🔒 Ghi chú bí mật';
            $note['content'] = 'Nhập mật khẩu để xem...';

        } else {

            $note['is_locked'] = 0;
        }

        unset($note['password_hash']);
    }

    echo json_encode($notes);

} catch (PDOException $e) {

    error_log($e->getMessage());

    echo json_encode([]);
}