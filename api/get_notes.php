<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

$my_id = $_SESSION['user_id'] ?? 0;

if (!$my_id) {
    echo json_encode([]);
    exit;
}

$view     = $_GET['view'] ?? 'all';
$label_id = isset($_GET['label_id']) && $_GET['label_id'] !== ''
    ? (int)$_GET['label_id']
    : null;

try {

    // =========================================================
    // SINGLE NOTE MODE (FIX VERSION SYNC)
    // =========================================================
    if (isset($_GET['note_id'])) {

        $note_id = (int)$_GET['note_id'];

        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$my_id]);
        $my_email = $meStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT n.*,
                   CASE
                       WHEN n.user_id = :my_id THEN 'owner'
                       ELSE (
                           SELECT permission
                           FROM shared_notes
                           WHERE note_id = n.id
                             AND recipient_email = :my_email
                           LIMIT 1
                       )
                   END AS permission
            FROM notes n
            WHERE n.id = :note_id
              AND n.is_trashed = 0
              AND (
                    n.user_id = :my_id
                    OR EXISTS (
                        SELECT 1
                        FROM shared_notes sn
                        WHERE sn.note_id = n.id
                          AND sn.recipient_email = :my_email
                    )
                  )
            LIMIT 1
        ");

        $stmt->execute([
            'note_id' => $note_id,
            'my_id'   => $my_id,
            'my_email'=> $my_email
        ]);

        $note = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($note ?: null);
        exit;
    }

    // =========================================================
    // SHARED NOTES
    // =========================================================
    if ($view === 'shared') {

        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$my_id]);
        $my_email = $meStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT n.*, 
                   u.display_name AS owner_name,
                   sn.permission,
                   sn.shared_at
            FROM shared_notes sn
            JOIN notes n ON sn.note_id = n.id
            JOIN users u ON n.user_id = u.id
            WHERE sn.recipient_email = :my_email
              AND n.user_id != :my_id
              AND n.is_trashed = 0
            ORDER BY sn.shared_at DESC, n.updated_at DESC
        ");

        $stmt->execute([
            'my_email' => $my_email,
            'my_id'    => $my_id
        ]);

    }

    // =========================================================
    // TRASH
    // =========================================================
    elseif ($view === 'trash') {

        $stmt = $pdo->prepare("
            SELECT *,
                   'owner' AS role
            FROM notes
            WHERE user_id = :my_id
              AND is_trashed = 1
            ORDER BY updated_at DESC
        ");

        $stmt->execute([
            'my_id' => $my_id
        ]);

    }

    // =========================================================
    // MY NOTES
    // =========================================================
    else {

        $sql = "
            SELECT n.*,
                   'owner' AS role
            FROM notes n
            WHERE n.user_id = :my_id
              AND n.is_trashed = 0
        ";

        $params = [
            'my_id' => $my_id
        ];

        if ($label_id) {

            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM note_labels nl
                    WHERE nl.note_id = n.id
                      AND nl.label_id = :label_id
                )
            ";

            $params['label_id'] = $label_id;
        }

        $sql .= "
            ORDER BY
                n.is_pinned DESC,
                n.pinned_at DESC,
                n.updated_at DESC,
                n.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notes);

} catch (PDOException $e) {

    error_log("Get Notes Error: " . $e->getMessage());

    echo json_encode([]);
}
?>