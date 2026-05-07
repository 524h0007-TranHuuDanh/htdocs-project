<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'list';

if ($action == 'list') {
    $stmt = $pdo->prepare("SELECT * FROM labels WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} 
elseif ($action == 'add') {
    $name = $_POST['name'] ?? '';
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
        $stmt->execute([$user_id, $name]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    }
}
elseif ($action == 'delete') {
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    echo json_encode(['success' => true]);
}
// Chức năng đổi tên nhãn sẽ tự cập nhật nhờ logic DB