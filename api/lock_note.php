<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$note_id = $_POST['note_id'] ?? 0;
$password = $_POST['password'] ?? '';
$action = $_POST['action'] ?? 'lock';

if ($action == 'lock' && !empty($password)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$hash, $note_id, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $_SESSION['user_id']]);
}
echo json_encode(['success' => true]);