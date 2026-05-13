<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json; charset=utf-8');

$note_id = $_POST['note_id'] ?? 0;
$label_id = $_POST['label_id'] ?? 0;
$action = $_POST['action'] ?? 'add'; // 'add' hoặc 'remove'

if ($action == 'add') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)");
    $stmt->execute([$note_id, $label_id]);
} else {
    $stmt = $pdo->prepare("DELETE FROM note_labels WHERE note_id = ? AND label_id = ?");
    $stmt->execute([$note_id, $label_id]);
}
echo json_encode(['success' => true]);