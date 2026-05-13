<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'list';

header('Content-Type: application/json');

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($action, ['add', 'rename', 'delete'], true)
) {
    require_valid_csrf_post();
}

try {
    if ($action == 'list') {
        $stmt = $pdo->prepare("SELECT * FROM labels WHERE user_id = ? ORDER BY name");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    elseif ($action == 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $name]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tên nhãn không được để trống']);
        }
    }
    elseif ($action == 'rename') {
        $id = intval($_POST['id'] ?? 0);
        $new_name = trim($_POST['name'] ?? '');

        if ($id <= 0 || empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE labels SET name = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_name, $id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đổi tên nhãn thành công']);
    }
    elseif ($action == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database']);
}
?>