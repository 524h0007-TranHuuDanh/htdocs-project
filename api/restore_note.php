<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? 0;
$stmt = $pdo->prepare("UPDATE notes SET is_trashed = 0 WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>