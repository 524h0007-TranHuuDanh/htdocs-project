<?php
session_start();
require_once '../database.php';
header('Content-Type: application/json');

$my_id = $_SESSION['user_id'] ?? 0;
if (!$my_id) {
    echo json_encode([]);
    exit;
}

$view = $_GET['view'] ?? 'all';

try {
    if ($view === 'shared') {
        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$my_id]);
        $my_email = $meStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT n.*, u.display_name AS owner_name, sn.permission
             FROM shared_notes sn
             JOIN notes n ON sn.note_id = n.id
             JOIN users u ON n.user_id = u.id
             WHERE sn.recipient_email = :my_email
               AND n.user_id != :my_id
               AND n.is_trashed = 0"
        );
        $stmt->execute(['my_email' => $my_email, 'my_id' => $my_id]);

    } elseif ($view === 'trash') {
        $stmt = $pdo->prepare(
            "SELECT *, 'owner' AS role FROM notes WHERE user_id = :my_id AND is_trashed = 1"
        );
        $stmt->execute(['my_id' => $my_id]);

    } else {
        $stmt = $pdo->prepare(
            "SELECT *, 'owner' AS role FROM notes WHERE user_id = :my_id AND is_trashed = 0"
        );
        $stmt->execute(['my_id' => $my_id]);
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}