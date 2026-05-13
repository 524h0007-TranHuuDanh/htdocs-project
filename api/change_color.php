<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? 0;
$color = $_POST['color'] ?? '';

$stmt = $pdo->prepare("UPDATE notes SET color = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$color, $id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>