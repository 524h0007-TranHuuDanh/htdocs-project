CẤU TRÚC THƯ MỤC:

HTDOCS
|
api - auth_helper.php
|   |
|   - change_color.php
|   |
|   - delete_image.php
|   |
|   - delete_note.php
|   |
|   - get_note_images.php
|   |
|   - get_note_labels.php
|   |
|   - get_notes.php
|   |
|   - get_shares.php
|   |
|   - lock_note.php
|   |
|   - manage_labels.php
|   |
|   - pin_note.php
|   |
|   - restore_note.php
|   |
|   - revoke_share.php
|   |
|   - save_note.php
|   |
|   - search.php
|   |
|   - set_note_label.php
|   |
|   - share_note.php
|   |
|   - update_profile.php
|   |
|   - upload_image.php
|   |
|   - verify_note.php
|
App - NoteWebSocket.php
|
assets - css - style.css
|      |
|      - js - app.js
uploads - avatars
|
vendor // đã cài đặt
|
websocket - server.php
|
activate.php
|
composer.json
|
composer.lock
|
config.php
|
database.php
|
database.sql
|
modals.php
|
index.php
|
login.php
|
logout.php
|
mail_config.php
|
manifest.json
|
register.php
|
reset_password.php
|
Rubrik.docx
|
service-worker.js

Nội dung code của tất cả các file

Thư mục API

auth_helper.php
//code//
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function check_login() {
    if (!is_logged_in()) {
        // Nếu là request AJAX thì trả JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
            exit();
        }
        
        // Request bình thường → redirect về login
        header("Location: login.php");
        exit();
    }
}
//----//

change_color.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$id = $_POST['id'] ?? 0;
$color = $_POST['color'] ?? '';

$stmt = $pdo->prepare("UPDATE notes SET color = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$color, $id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>
//----//

delete_image.php
//code//
<?php
// api/delete_image.php
// SỬA WARN: Thêm kiểm tra quyền sở hữu trước khi xóa ảnh
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID ảnh không hợp lệ.']);
    exit;
}

try {
    // SỬA: Kiểm tra ảnh này có thuộc về ghi chú của user hiện tại không
    $stmt = $pdo->prepare(
        "SELECT ni.id, ni.file_path
         FROM note_images ni
         JOIN notes n ON ni.note_id = n.id
         WHERE ni.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$id, $user_id]);
    $img = $stmt->fetch();

    if (!$img) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa ảnh này.']);
        exit;
    }

    // Xóa file vật lý
    $physical_path = '../' . $img['file_path'];
    if (file_exists($physical_path)) {
        unlink($physical_path);
    }

    // Xóa record trong database
    $stmt = $pdo->prepare("DELETE FROM note_images WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
//----//

delete_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id       = intval($_POST['id'] ?? 0);
$action   = $_POST['action'] ?? 'trash';
$password = $_POST['delete_password'] ?? '';
$user_id  = $_SESSION['user_id'];

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú hoặc bạn không có quyền!']);
        exit;
    }

    if (!empty($note['password_hash'])) {
        if (empty($password)) {
            echo json_encode([
                'success' => false,
                'require_password' => true,
                'message' => 'Ghi chú này được bảo vệ bằng mật khẩu. Vui lòng nhập mật khẩu để xóa!'
            ]);
            exit;
        }
        if (!password_verify($password, $note['password_hash'])) {
            echo json_encode([
                'success' => false,
                'require_password' => true,
                'message' => 'Mật khẩu không đúng!'
            ]);
            exit;
        }
    }

    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Đã chuyển vào thùng rác']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true, 'message' => 'Đã xóa vĩnh viễn']);
    }

} catch (Exception $e) {
    error_log("Delete Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại!']);
}
?>
//

get_note_images.php
//code//
<?php
// api/get_note_images.php
// SỬA CRIT 2: Cho phép người được chia sẻ xem ảnh của ghi chú
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id = intval($_GET['note_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Lấy email của user hiện tại để kiểm tra shared_notes
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Truy vấn kiểm tra cả chủ sở hữu VÀ người được chia sẻ
    $sql = "SELECT ni.id, ni.file_path
            FROM note_images ni
            JOIN notes n ON ni.note_id = n.id
            WHERE ni.note_id = ?
              AND (
                n.user_id = ?
                OR EXISTS (
                    SELECT 1 FROM shared_notes
                    WHERE note_id = n.id
                      AND recipient_email = ?
                )
              )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$note_id, $user_id, $my_email]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($images);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
//----//

get_note_labels.php
//code//
<?php
// api/get_note_labels.php
// SỬA WARN: Kiểm tra note thuộc về user hoặc được chia sẻ trước khi trả nhãn
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id = intval($_GET['note_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Kiểm tra quyền truy cập vào note trước
    $accessCheck = $pdo->prepare(
        "SELECT id FROM notes WHERE id = ? AND user_id = ?
         UNION
         SELECT n.id FROM notes n
         JOIN shared_notes sn ON sn.note_id = n.id
         WHERE n.id = ? AND sn.recipient_email = ?"
    );
    $accessCheck->execute([$note_id, $user_id, $note_id, $my_email]);

    if (!$accessCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT l.* FROM labels l
         JOIN note_labels nl ON l.id = nl.label_id
         WHERE nl.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}
//----//

get_notes.php
//codee//
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
//----//

get_shares.php
//code//
<?php
// api/get_shares.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$note_id = intval($_GET['note_id'] ?? 0);

try {
    // Kiểm tra note thuộc về user hiện tại (chỉ chủ mới xem được danh sách share)
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $user_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sn.id              AS share_id,
            sn.recipient_email AS email,
            COALESCE(u.display_name, sn.recipient_email) AS display_name,
            sn.permission
         FROM shared_notes sn
         LEFT JOIN users u ON u.email = sn.recipient_email
         WHERE sn.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}
//----//

lock_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id      = intval($_POST['note_id'] ?? 0);
$action       = $_POST['action'] ?? ''; 
$password     = $_POST['password'] ?? '';           // new password
$confirm_pass = $_POST['confirm_password'] ?? '';   // confirm new
$old_password = $_POST['old_password'] ?? '';
$user_id      = $_SESSION['user_id'];

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    $current_hash = $note['password_hash'];

    // ====================== LOCK - ĐẶT MẬT KHẨU MỚI ======================
    if ($action === 'lock') {
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 4 ký tự!']);
            exit;
        }
        if ($password !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$hash, $note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã khóa ghi chú thành công']);

    // ====================== UNLOCK ======================
    } elseif ($action === 'unlock') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        $pdo->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?")
            ->execute([$note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã mở khóa ghi chú']);

    // ====================== CHANGE PASSWORD ======================
    } elseif ($action === 'change') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng!']);
            exit;
        }
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 4 ký tự!']);
            exit;
        }
        if ($password !== $confirm_pass) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu xác nhận không khớp!']);
            exit;
        }
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$new_hash, $note_id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đã đổi mật khẩu thành công']);

    // ====================== VERIFY ======================
    } elseif ($action === 'verify') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
    }

} catch (PDOException $e) {
    error_log("Lock Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
?>
//----//

manage_labels.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'list';

header('Content-Type: application/json');

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
//----//

pin_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';
$is_pinned = $_POST['is_pinned'] ?? 0;

if (empty($id)) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $pinned_at = $is_pinned ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE notes SET is_pinned = ?, pinned_at = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$is_pinned, $pinned_at, $id, $user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
//----//

restore_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$id = $_POST['id'] ?? 0;
$stmt = $pdo->prepare("UPDATE notes SET is_trashed = 0 WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>
//----//

revoke_share.php
//code//
<?php
// api/revoke_share.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$share_id = intval($_POST['share_id'] ?? 0);

try {
    // Chỉ chủ ghi chú mới được thu hồi quyền
    $stmt = $pdo->prepare(
        "DELETE sn FROM shared_notes sn
         JOIN notes n ON sn.note_id = n.id
         WHERE sn.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$share_id, $user_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
//----//

save_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';

check_login();

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
    // VERSION CONFLICT CHECK
    // =========================================================
    if (
        $client_version > 0 &&
        $client_version < ($note['version'] - 3)
    ) {

        echo json_encode([
            'success'  => false,
            'conflict' => true,
            'version'  => $note['version'],
            'message'  => 'Có xung đột phiên bản. Đang tải lại ghi chú...'
        ]);

        exit;
    }

    // =========================================================
    // UPDATE NOTE
    // =========================================================
    $new_version = $note['version'] + 1;

    $stmt = $pdo->prepare("
        UPDATE notes
        SET
            title = ?,
            content = ?,
            version = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $title,
        $content,
        $new_version,
        $id
    ]);

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

    // WebSocket server sẽ xử lý ở đây

}
?>
//----//

search.php
//code//
<?php
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

    if ($view_mode === 'shared') {

        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$user_id]);
        $my_email = $meStmt->fetchColumn();

        $sql = "
            SELECT 
                n.*,
                sn.permission,
                u.display_name AS owner_name,
                sn.shared_at,
                n.version
            FROM shared_notes sn
            JOIN notes n ON sn.note_id = n.id
            JOIN users u ON n.user_id = u.id
            WHERE sn.recipient_email = ? 
              AND n.user_id != ?
              AND n.is_trashed = 0
        ";

        $params[] = $my_email;
        $params[] = $user_id;

    } elseif ($view_mode === 'trash') {

        $sql = "
            SELECT n.*, 'owner' AS role, n.version 
            FROM notes n 
            WHERE n.user_id = ? AND n.is_trashed = 1
        ";
        $params[] = $user_id;

    } else {

        $sql = "
            SELECT n.*, 'owner' AS role, n.version 
            FROM notes n 
            WHERE n.user_id = ? AND n.is_trashed = 0
        ";
        $params[] = $user_id;

        if ($label_id && $label_id !== 'null') {
            $sql .= " AND n.id IN (SELECT note_id FROM note_labels WHERE label_id = ?)";
            $params[] = intval($label_id);
        }
    }

    $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    

    if ($view_mode === 'shared') {
        $sql .= " ORDER BY sn.shared_at DESC, n.updated_at DESC";
    } else {
        $sql .= " ORDER BY n.is_pinned DESC, n.pinned_at DESC, n.updated_at DESC, n.created_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hide locked content
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

//----//

set_note_label.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

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
//----//

shared_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();

header('Content-Type: application/json');

$sender_id  = $_SESSION['user_id'];
$note_id    = intval($_POST['note_id'] ?? 0);
$share_with = trim($_POST['share_with'] ?? '');
$permission = $_POST['permission'] ?? 'read';

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF)']);
    exit;
}

if ($note_id <= 0 || empty($share_with)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
    exit;
}

if (!in_array($permission, ['read', 'edit'])) $permission = 'read';

try {
    // Kiểm tra owner
    $noteCheck = $pdo->prepare("SELECT id, title FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $sender_id]);
    $note = $noteCheck->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
        exit;
    }

    $emails = array_unique(array_map('trim', explode(',', $share_with)));
    $successCount = 0;
    $messages = [];

    $senderName = $_SESSION['display_name'] ?? 'Một người dùng';

    foreach ($emails as $email) {
        if (empty($email)) continue;

        $stmt = $pdo->prepare("SELECT id, email, display_name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $receiver = $stmt->fetch();

        if (!$receiver || $receiver['id'] == $sender_id) {
            $messages[] = "Không hợp lệ: " . htmlspecialchars($email);
            continue;
        }

        $recipient_email = $receiver['email'];

        // Kiểm tra đã share chưa
        $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
        $check->execute([$note_id, $recipient_email]);

        if ($check->fetch()) {
            $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
            $update->execute([$permission, $note_id, $recipient_email]);
            $messages[] = "Đã cập nhật quyền cho " . htmlspecialchars($receiver['display_name']);
        } else {
            $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)");
            $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
            
            // Gửi email thông báo
            sendShareNotification($recipient_email, $receiver['display_name'], $senderName, $note['title']);
            
            $messages[] = "Đã chia sẻ thành công cho " . htmlspecialchars($receiver['display_name']);
        }
        $successCount++;
    }

    echo json_encode([
        'success' => $successCount > 0,
        'message' => $successCount > 0 
            ? "Đã chia sẻ cho $successCount người.\n" . implode("\n", $messages)
            : "Không chia sẻ được cho ai."
    ]);

} catch (PDOException $e) {
    error_log("Share Note Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi database']);
}
?>
//----//

update_profile.php
//code//
<?php
/**
 * API: Cập nhật thông tin profile người dùng
 * Đã cải tiến: CSRF, Security, Validation, Error Handling
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

// ====================== SECURITY CHECK ======================
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    echo json_encode([
        'success' => false,
        'message' => 'Yêu cầu không hợp lệ (CSRF)'
    ]);
    exit;
}

// ====================== DEFAULT VALUES ======================
$default_avatar = 'uploads/avatars/default-avatar.png';

// ====================== INPUT VALIDATION ======================
$font_size   = $_POST['font_size']   ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$note_color  = $_POST['note_color']  ?? '#ffffff';

$allowed_fonts  = ['14px', '16px', '18px'];
$allowed_themes = ['light', 'dark'];

if (!in_array($font_size, $allowed_fonts)) {
    $font_size = '16px';
}

if (!in_array($theme_color, $allowed_themes)) {
    $theme_color = 'light';
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $note_color)) {
    $note_color = '#ffffff';
}

// ====================== CURRENT AVATAR ======================
$avatar_path = !empty($_SESSION['avatar']) 
    ? $_SESSION['avatar'] 
    : $default_avatar;

try {
    // ====================== UPLOAD AVATAR ======================
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $upload_dir = __DIR__ . '/../uploads/avatars/';

        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Kiểm tra kích thước (tối đa 2MB)
        $max_size = 2 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception('Ảnh đại diện không được vượt quá 2MB');
        }

        // Kiểm tra MIME type an toàn
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime_type, $allowed_mimes)) {
            throw new Exception('Chỉ chấp nhận file ảnh (jpg, png, gif, webp)');
        }

        // Tạo tên file an toàn
        $ext_map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $ext_map[$mime_type];
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
        $target_path = $upload_dir . $new_filename;
        $db_path = 'uploads/avatars/' . $new_filename;

        // Upload file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new Exception('Không thể tải lên ảnh đại diện');
        }

        // Xóa avatar cũ nếu không phải avatar mặc định
        if (!empty($_SESSION['avatar']) && $_SESSION['avatar'] !== $default_avatar) {
            $old_file = __DIR__ . '/../' . $_SESSION['avatar'];
            if (file_exists($old_file)) {
                @unlink($old_file);
            }
        }

        $avatar_path = $db_path;
    }

    // ====================== UPDATE DATABASE ======================
    $stmt = $pdo->prepare("
        UPDATE users 
        SET font_size = ?, 
            theme_color = ?, 
            note_color = ?, 
            avatar = ? 
        WHERE id = ?
    ");

    $stmt->execute([
        $font_size,
        $theme_color,
        $note_color,
        $avatar_path,
        $user_id
    ]);

    // ====================== UPDATE SESSION ======================
    $_SESSION['font_size']   = $font_size;
    $_SESSION['theme_color'] = $theme_color;
    $_SESSION['note_color']  = $note_color;
    $_SESSION['avatar']      = $avatar_path;

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thông tin thành công',
        'avatar'  => $avatar_path
    ]);

} catch (Exception $e) {
    error_log("Update Profile Error (User ID: $user_id): " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Lỗi hệ thống. Vui lòng thử lại sau.'
    ]);
}
//----//

upload_image.php
//code//
<?php
// api/upload_image.php
// SỬA WARN: Dùng finfo_file() kiểm tra MIME server-side thay vì tin $_FILES['type']
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image']) || !isset($_POST['note_id'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$note_id = intval($_POST['note_id']);
$user_id = $_SESSION['user_id'];
$file    = $_FILES['image'];

// Kiểm tra lỗi upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Lỗi upload file (code: ' . $file['error'] . ').']);
    exit;
}

// Kiểm tra note_id có thuộc về user này không (hoặc được share với quyền edit)
$meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$meStmt->execute([$user_id]);
$my_email = $meStmt->fetchColumn();

$noteCheck = $pdo->prepare(
    "SELECT id FROM notes WHERE id = ? AND user_id = ?
     UNION
     SELECT n.id FROM notes n
     JOIN shared_notes sn ON sn.note_id = n.id
     WHERE n.id = ? AND sn.recipient_email = ? AND sn.permission = 'edit'"
);
$noteCheck->execute([$note_id, $user_id, $note_id, $my_email]);
if (!$noteCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm ảnh vào ghi chú này.']);
    exit;
}

// Giới hạn kích thước file: 5MB
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File quá lớn. Tối đa 5MB.']);
    exit;
}

// SỬA: Kiểm tra MIME type phía server bằng finfo (không tin client)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ cho phép file ảnh (jpg, png, gif, webp).']);
    exit;
}

// Map MIME -> extension an toàn
$ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$extension = $ext_map[$mime_type];

// Tạo tên file duy nhất
$new_filename = uniqid('img_', true) . '.' . $extension;
$upload_dir   = '../uploads/';
$upload_path  = $upload_dir . $new_filename;
$db_path      = 'uploads/' . $new_filename;

// Tạo thư mục nếu chưa có
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $stmt = $pdo->prepare("INSERT INTO note_images (note_id, file_path) VALUES (?, ?)");
    $stmt->execute([$note_id, $db_path]);

    echo json_encode([
        'success'  => true,
        'file_path' => $db_path,
        'image_id' => $pdo->lastInsertId()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu file vào thư mục uploads.']);
}
//----//

verify_note.php
//code//
<?php
// api/verify_note.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$note_id  = intval($_POST['note_id']  ?? 0);
$password = $_POST['password'] ?? '';

try {
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // Tìm ghi chú: chủ sở hữu HOẶC được share qua email
    $stmt = $pdo->prepare("
        SELECT n.title, n.content, n.password_hash, n.color, n.user_id,
               (SELECT permission FROM shared_notes
                WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
        FROM notes n
        WHERE n.id = ?
          AND (
            n.user_id = ?
            OR EXISTS (SELECT 1 FROM shared_notes WHERE note_id = n.id AND recipient_email = ?)
          )
    ");
    $stmt->execute([$my_email, $note_id, $user_id, $my_email]);
    $note = $stmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    if (!password_verify($password, $note['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
        exit;
    }

    $perm = ($note['user_id'] == $user_id) ? 'owner' : $note['permission'];
    echo json_encode([
        'success'    => true,
        'title'      => $note['title'],
        'content'    => $note['content'],
        'color'      => $note['color'],
        'permission' => $perm
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
//----//

Thư mục App

NoteWebSocket.php
//code//
<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $noteSubscriptions = []; // note_id => [resourceId => ['conn' => conn, 'user_name' => str]]

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(['type' => 'ping']));
        $conn->user_id   = 0;
        $conn->user_name = 'Unknown';
        echo "[WS] New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        switch ($data['type']) {

            case 'auth':
                $uid  = intval($data['user_id'] ?? 0);
                $name = $data['user_name'] ?? 'User';
                if ($uid > 0) {
                    $from->user_id   = $uid;
                    $from->user_name = $name;
                    $from->send(json_encode(['type' => 'auth_success']));
                    echo "[WS] User $uid ($name) authenticated\n";
                }
                break;

            case 'join_note':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && $from->user_id > 0) {
                    // Thêm vào phòng
                    $this->noteSubscriptions[$note_id][$from->resourceId] = [
                        'conn'      => $from,
                        'user_name' => $from->user_name
                    ];
                    echo "[WS] User {$from->user_id} joined note $note_id\n";

                    // Broadcast danh sách người đang xem
                    $this->broadcastPresence($note_id);
                }
                break;

            case 'leave_note':
                $note_id = intval($data['note_id'] ?? 0);
                $this->removeFromNote($from, $note_id);
                break;

            case 'update':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && isset($this->noteSubscriptions[$note_id])) {

                    $broadcastData = [
                        'type'       => 'update',
                        'note_id'    => $note_id,
                        'user_name'  => $from->user_name,
                        'title'      => $data['title'] ?? null,
                        'content'    => $data['content'] ?? null,
                        'sender_id'  => $from->resourceId,
                        'timestamp'  => time()
                    ];

                    // Broadcast cho tất cả người KHÁC trong note (không echo lại người gửi)
                    foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
                        if ($info['conn'] !== $from) {
                            $info['conn']->send(json_encode($broadcastData));
                        }
                    }
                }
                break;
        }
    }

    /**
     * Broadcast danh sách người đang xem ghi chú
     */
    private function broadcastPresence(int $note_id) {
        if (!isset($this->noteSubscriptions[$note_id])) return;

        $users = array_values(array_map(
            fn($info) => $info['user_name'],
            $this->noteSubscriptions[$note_id]
        ));

        $payload = json_encode([
            'type'     => 'presence',
            'note_id'  => $note_id,
            'users'    => $users
        ]);

        foreach ($this->noteSubscriptions[$note_id] as $info) {
            $info['conn']->send($payload);
        }
    }

    private function removeFromNote(ConnectionInterface $conn, int $note_id) {
        if ($note_id && isset($this->noteSubscriptions[$note_id])) {
            unset($this->noteSubscriptions[$note_id][$conn->resourceId]);

            if (empty($this->noteSubscriptions[$note_id])) {
                unset($this->noteSubscriptions[$note_id]);
            } else {
                $this->broadcastPresence($note_id);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Xóa khỏi tất cả các note đang tham gia
        foreach (array_keys($this->noteSubscriptions) as $note_id) {
            $this->removeFromNote($conn, (int)$note_id);
        }
        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

//----//

Thư mục assets
css
style.css
//code//
/* =========================================================
   LIQUID GLASS PRO — iOS 26 Edition (v3 · Crystal Clear)
   - Drastically more transparent surfaces (true liquid glass)
   - Enforced text contrast: dark text on light, light text on dark
   - Heavier saturate+brightness on backdrop to keep legibility
   - GPU-friendly transforms only
   - Drop-in replacement: all class names preserved
   ========================================================= */

:root {

    /* ===== CORE PALETTE ===== */
    --primary:          #5B9BFF;
    --primary-2:        #8E7BFF;
    --primary-3:        #FF7AB6;
    --accent-aqua:      #2DD4E5;
    --accent-mint:      #6EE7B7;
    --accent-gold:      #FBBF24;

    --light-bg:         #EEF3FF;
    --light-bg-2:       #DCE7FF;
    --dark-bg:          #05091A;
    --dark-bg-2:        #0A1226;

    /* ===== TEXT TOKENS — ENFORCED CONTRAST ===== */
    --text-on-glass-light:        #0A1024;   /* near-black for light mode */
    --text-on-glass-light-soft:   #2A344E;
    --text-on-glass-light-muted:  #4A5470;

    --text-on-glass-dark:         #F4F8FF;   /* near-white for dark mode */
    --text-on-glass-dark-soft:    #D6E0F2;
    --text-on-glass-dark-muted:   #9BAACB;

    /* ===== LIQUID GLASS — TRUE PANE OF GLASS ===== */
    /* Almost invisible surface, lifted only by border + highlight + blur */
    --glass-bg-light:
        linear-gradient(150deg,
            rgba(255,255,255,0.12) 0%,
            rgba(255,255,255,0.04) 50%,
            rgba(220,232,255,0.07) 100%
        );

    --glass-bg-dark:
        linear-gradient(150deg,
            rgba(255,255,255,0.045) 0%,
            rgba(255,255,255,0.015) 50%,
            rgba(180,200,255,0.03) 100%
        );

    --glass-border-light: rgba(255,255,255,0.35);
    --glass-border-dark:  rgba(255,255,255,0.10);

    --glass-shadow-light:
        0 1px 0 rgba(255,255,255,0.55) inset,
        0 -1px 0 rgba(255,255,255,0.14) inset,
        0 10px 30px -8px rgba(15,23,42,0.14),
        0 30px 60px -20px rgba(15,23,42,0.10);

    --glass-shadow-dark:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 10px 30px -8px rgba(0,0,0,0.55),
        0 30px 60px -20px rgba(0,0,0,0.45);

    /* Stronger saturate+brightness to keep colors vibrant through clear glass */
    --backdrop-glass:    blur(28px) saturate(200%) brightness(1.08);
    --backdrop-glass-lg: blur(40px) saturate(210%) brightness(1.10);
    --backdrop-glass-xl: blur(56px) saturate(220%) brightness(1.12);
    --backdrop-glass-dark:    blur(28px) saturate(180%) brightness(0.92);
    --backdrop-glass-dark-lg: blur(40px) saturate(190%) brightness(0.94);
    --backdrop-glass-dark-xl: blur(56px) saturate(200%) brightness(0.96);

    --radius-xl: 32px;
    --radius-lg: 24px;
    --radius-md: 18px;
    --radius-sm: 12px;

    --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
    --ease-smooth: cubic-bezier(0.22, 1, 0.36, 1);
    --ease-glass:  cubic-bezier(0.16, 1, 0.3, 1);

    --note-default-color: #ffffff;
}

/* =========================================================
   RESET & BASE
   ========================================================= */
*, *::before, *::after { box-sizing: border-box; }

html { color-scheme: light dark; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', system-ui, sans-serif;
    min-height: 100vh;
    overflow-x: hidden;
    color: var(--text-on-glass-light);
    background-color: #C9DCFF;
    background-image:
        radial-gradient(ellipse 70% 55% at 18% 12%,
            color-mix(in oklab, var(--primary) 38%, transparent) 0%,
            transparent 60%),
        radial-gradient(ellipse 55% 45% at 82% 18%,
            color-mix(in oklab, var(--primary-2) 32%, transparent) 0%,
            transparent 55%),
        radial-gradient(ellipse 65% 50% at 55% 88%,
            color-mix(in oklab, var(--primary-3) 22%, transparent) 0%,
            transparent 55%),
        radial-gradient(ellipse 70% 55% at 88% 72%,
            color-mix(in oklab, var(--accent-aqua) 20%, transparent) 0%,
            transparent 60%),
        linear-gradient(160deg,
            #D6E6FF 0%, #ECF2FF 35%, #E0EAFF 65%, #CDDDFF 100%);
    background-attachment: fixed;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
    font-size: 16px;
}

body::before, body::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    z-index: -1;
    pointer-events: none;
    will-change: transform;
    contain: strict;
}
body::before {
    width: 560px; height: 560px;
    top: -180px; right: -160px;
    background: radial-gradient(circle at 35% 35%,
        color-mix(in oklab, var(--primary) 55%, transparent) 0%,
        color-mix(in oklab, var(--primary-2) 30%, transparent) 45%,
        transparent 72%);
    filter: blur(60px);
    animation: orbDrift1 22s ease-in-out infinite;
}
body::after {
    width: 500px; height: 500px;
    bottom: -160px; left: -140px;
    background: radial-gradient(circle at 65% 65%,
        color-mix(in oklab, var(--primary-2) 45%, transparent) 0%,
        color-mix(in oklab, var(--primary-3) 28%, transparent) 50%,
        transparent 72%);
    filter: blur(70px);
    animation: orbDrift2 26s ease-in-out infinite;
}
@keyframes orbDrift1 {
    0%,100% { transform: translate3d(0,0,0) scale(1); }
    50%     { transform: translate3d(-28px,36px,0) scale(1.08); }
}
@keyframes orbDrift2 {
    0%,100% { transform: translate3d(0,0,0) scale(1); }
    50%     { transform: translate3d(32px,-42px,0) scale(1.10); }
}

/* =========================================================
   GLASS BASE — true crystal clear
   ========================================================= */
.glass {
    background: var(--glass-bg-light);
    -webkit-backdrop-filter: var(--backdrop-glass);
    backdrop-filter: var(--backdrop-glass);
    border: 1px solid var(--glass-border-light);
    box-shadow: var(--glass-shadow-light);
    position: relative;
    overflow: hidden;
    color: var(--text-on-glass-light);
    transition:
        box-shadow 0.4s var(--ease-smooth),
        border-color 0.4s var(--ease-smooth);
}

.glass::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(140deg,
        rgba(255,255,255,0.42) 0%,
        rgba(255,255,255,0.06) 28%,
        transparent 55%);
    pointer-events: none;
    border-radius: inherit;
    z-index: 1;
}

/* =========================================================
   NAVBAR — clear, crisp text
   ========================================================= */
.navbar {
    margin: 16px 20px;
    border-radius: 36px !important;
    position: relative;
    background: linear-gradient(145deg,
        rgba(255,255,255,0.24) 0%,
        rgba(255,255,255,0.08) 50%,
        rgba(216,232,255,0.14) 100%) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-lg);
    backdrop-filter: var(--backdrop-glass-lg);
    border: 1px solid rgba(255,255,255,0.40) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.55) inset,
        0 -1px 0 rgba(255,255,255,0.14) inset,
        0 12px 36px -10px rgba(15,23,42,0.14),
        0 30px 60px -24px rgba(15,23,42,0.10) !important;
    overflow: visible !important;
    color: var(--text-on-glass-light);
    transition:
        box-shadow 0.5s var(--ease-smooth),
        background 0.5s var(--ease-smooth);
    animation: navbarReveal 0.7s var(--ease-glass) both;
}
@keyframes navbarReveal {
    from { opacity: 0; transform: translate3d(0,-16px,0) scale(0.98); }
    to   { opacity: 1; transform: translate3d(0,0,0) scale(1); }
}
.navbar::before {
    content: '';
    position: absolute;
    top: 0; left: 16px; right: 16px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.85) 50%, transparent);
    border-radius: 999px;
    pointer-events: none;
    z-index: 2;
}

/* Brand & navbar text — strong contrast */
.navbar-brand {
    font-weight: 700 !important;
    font-size: 1.25rem;
    letter-spacing: -0.3px;
    color: var(--text-on-glass-light) !important;
    position: relative; z-index: 3;
    transition: opacity 0.2s;
}
.navbar-brand:hover { opacity: 0.78; }
.navbar .small,
.navbar .nav-link,
.navbar span {
    color: var(--text-on-glass-light-soft) !important;
    font-weight: 600;
    position: relative; z-index: 3;
}

/* =========================================================
   SEARCH INPUT
   ========================================================= */
#searchInput {
    border: 1px solid rgba(255,255,255,0.45) !important;
    border-radius: 22px !important;
    background: rgba(255,255,255,0.28) !important;
    -webkit-backdrop-filter: blur(22px) saturate(190%) brightness(1.06);
    backdrop-filter: blur(22px) saturate(190%) brightness(1.06);
    padding: 12px 20px;
    color: var(--text-on-glass-light) !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.65),
        inset 0 -1px 0 rgba(0,0,0,0.03),
        0 4px 14px -2px rgba(15,23,42,0.06);
    transition:
        background 0.32s var(--ease-glass),
        box-shadow 0.32s var(--ease-glass),
        transform 0.32s var(--ease-glass);
}
#searchInput:focus {
    transform: translate3d(0,-1px,0);
    background: rgba(255,255,255,0.55) !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.85),
        0 8px 28px -4px color-mix(in oklab, var(--primary) 40%, transparent),
        0 0 0 3px color-mix(in oklab, var(--primary) 22%, transparent);
    outline: none;
    border-color: color-mix(in oklab, var(--primary) 50%, transparent) !important;
}
#searchInput::placeholder { color: rgba(74,84,112,0.62); transition: color 0.3s; }
#searchInput:focus::placeholder { color: rgba(74,84,112,0.42); }

/* =========================================================
   TOOLBAR
   ========================================================= */
.bg-body-tertiary {
    background: linear-gradient(145deg,
        rgba(255,255,255,0.20) 0%,
        rgba(255,255,255,0.06) 100%) !important;
    border: 1px solid rgba(255,255,255,0.36) !important;
    -webkit-backdrop-filter: var(--backdrop-glass);
    backdrop-filter: var(--backdrop-glass);
    border-radius: 26px !important;
    color: var(--text-on-glass-light);
    box-shadow:
        0 1px 0 rgba(255,255,255,0.50) inset,
        0 8px 28px -6px rgba(15,23,42,0.08);
    transition: box-shadow 0.4s var(--ease-smooth);
}

/* =========================================================
   NOTES GRID
   ========================================================= */
.note-grid-view {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 22px;
}
.note-list-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* =========================================================
   NOTE CARD — crystal clear glass
   ========================================================= */
.note-card {
    position: relative;
    border-radius: 28px !important;
    overflow: hidden;
    isolation: isolate;
    border: 1px solid rgba(255,255,255,0.32) !important;
    background: linear-gradient(155deg,
        rgba(255,255,255,0.10) 0%,
        rgba(255,255,255,0.03) 45%,
        rgba(212,228,255,0.05) 75%,
        rgba(255,255,255,0.02) 100%);
    -webkit-backdrop-filter: var(--backdrop-glass);
    backdrop-filter: var(--backdrop-glass);
    color: var(--text-on-glass-light);
    box-shadow:
        0 1px 0 rgba(255,255,255,0.55) inset,
        0 -1px 0 rgba(255,255,255,0.14) inset,
        0 10px 30px -8px rgba(15,23,42,0.12),
        0 30px 60px -24px rgba(15,23,42,0.10);
    transition:
        transform    0.45s var(--ease-spring),
        box-shadow   0.4s  var(--ease-smooth),
        border-color 0.3s  var(--ease-smooth);
    cursor: pointer;
    animation: cardPop 0.55s var(--ease-glass) both;
    will-change: transform;
    content-visibility: auto;
    contain-intrinsic-size: 240px;
}

.note-card::before {
    content: '';
    position: absolute;
    inset: 0;
    padding: 1px;
    border-radius: inherit;
    background: linear-gradient(145deg,
        rgba(255,255,255,0.75) 0%,
        color-mix(in oklab, var(--primary) 30%, transparent) 25%,
        rgba(255,255,255,0.08) 50%,
        color-mix(in oklab, var(--primary-2) 26%, transparent) 75%,
        rgba(255,255,255,0.45) 100%);
    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
            mask-composite: exclude;
    pointer-events: none;
    z-index: 2;
    opacity: 0.65;
    transition: opacity 0.35s var(--ease-smooth);
}

.note-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(140deg,
        rgba(255,255,255,0.32) 0%,
        rgba(255,255,255,0.04) 30%,
        transparent 55%);
    pointer-events: none;
    z-index: 1;
    border-radius: inherit;
}

.note-card .shine-sweep {
    position: absolute; inset: 0;
    z-index: 3; pointer-events: none;
    overflow: hidden; border-radius: inherit;
}
.note-card .shine-sweep::before {
    content: '';
    position: absolute;
    top: 0; left: -120%;
    width: 60%; height: 100%;
    background: linear-gradient(105deg,
        transparent, rgba(255,255,255,0.36),
        rgba(255,255,255,0.10), transparent);
    transform: skewX(-18deg) translate3d(0,0,0);
    transition: left 0.7s var(--ease-smooth);
}
.note-card:hover .shine-sweep::before { left: 160%; }

.note-card:hover {
    transform: translate3d(0,-10px,0) scale(1.02);
    box-shadow:
        0 1px 0 rgba(255,255,255,0.65) inset,
        0 24px 56px -12px rgba(15,23,42,0.18),
        0 0 0 1px color-mix(in oklab, var(--primary) 18%, transparent),
        0 0 40px -8px color-mix(in oklab, var(--primary) 28%, transparent);
    border-color: rgba(255,255,255,0.60) !important;
}
.note-card:hover::before { opacity: 1; }
.note-card:active {
    transform: translate3d(0,-3px,0) scale(0.99);
    transition-duration: 0.14s;
}

/* =========================================================
   CARD BODY — enforce dark text on light
   ========================================================= */
.card-body {
    padding: 22px 24px !important;
    position: relative;
    z-index: 4;
}
.card-title {
    font-weight: 700;
    font-size: 1.08rem;
    letter-spacing: -0.2px;
    color: var(--text-on-glass-light) !important;
    margin-bottom: 8px;
}
.card-text {
    line-height: 1.7;
    color: var(--text-on-glass-light-soft) !important;
    opacity: 0.95;
    font-size: 0.92rem;
}

/* =========================================================
   BUTTONS
   ========================================================= */
.btn {
    border: none !important;
    border-radius: 18px !important;
    padding: 10px 20px;
    font-weight: 600;
    letter-spacing: 0.1px;
    transition:
        transform  0.28s var(--ease-spring),
        box-shadow 0.28s var(--ease-smooth),
        background 0.28s var(--ease-smooth),
        filter     0.28s;
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    backdrop-filter: blur(16px) saturate(180%);
    position: relative;
    overflow: hidden;
    will-change: transform;
}
.btn::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(145deg,
        rgba(255,255,255,0.28) 0%, transparent 50%);
    border-radius: inherit;
    pointer-events: none;
    transition: opacity 0.3s;
}
.btn:hover {
    transform: translate3d(0,-2px,0) scale(1.02);
    filter: brightness(1.05);
}
.btn:active {
    transform: translate3d(0,0,0) scale(0.97);
    transition-duration: 0.10s;
}

.btn-primary {
    background: linear-gradient(135deg,
        var(--primary) 0%,
        var(--primary-2) 100%) !important;
    color: #fff !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.30) inset,
        0 10px 24px -6px color-mix(in oklab, var(--primary) 50%, transparent),
        0 4px 10px -2px color-mix(in oklab, var(--primary-2) 35%, transparent);
}
.btn-primary:hover {
    box-shadow:
        0 1px 0 rgba(255,255,255,0.35) inset,
        0 16px 36px -6px color-mix(in oklab, var(--primary) 60%, transparent),
        0 6px 16px -2px color-mix(in oklab, var(--primary-2) 45%, transparent);
}

.btn-outline-secondary,
.btn-light {
    background: rgba(255,255,255,0.22) !important;
    border: 1px solid rgba(255,255,255,0.45) !important;
    color: var(--text-on-glass-light) !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.55),
        0 4px 14px -2px rgba(15,23,42,0.08);
}
.btn-outline-secondary:hover,
.btn-light:hover {
    background: rgba(255,255,255,0.42) !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.75),
        0 8px 22px -4px rgba(15,23,42,0.12);
}

/* Bootstrap success/danger/warning glass tints — keep solid for clarity */
.btn-success { color: #fff !important; }
.btn-danger  { color: #fff !important; }
.btn-warning { color: #2A2200 !important; }

/* =========================================================
   FLOATING ACTION BUTTON
   ========================================================= */
.floating-create {
    position: fixed;
    right: 28px; bottom: 28px;
    width: 64px; height: 64px;
    border: none;
    border-radius: 50%;
    z-index: 999;
    color: #fff;
    font-size: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg,
        #7BB4FF 0%, var(--primary) 45%, var(--primary-2) 100%);
    box-shadow:
        0 1px 0 rgba(255,255,255,0.35) inset,
        0 18px 40px -8px color-mix(in oklab, var(--primary) 55%, transparent),
        0 8px 18px -4px color-mix(in oklab, var(--primary-2) 40%, transparent);
    transition:
        transform 0.4s var(--ease-spring),
        box-shadow 0.4s var(--ease-smooth);
    overflow: hidden;
    animation: fabReveal 0.7s 0.25s var(--ease-spring) both;
    will-change: transform;
}
@keyframes fabReveal {
    from { opacity: 0; transform: scale(0) rotate(-90deg); }
    to   { opacity: 1; transform: scale(1) rotate(0deg); }
}
.floating-create::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(135deg,
        rgba(255,255,255,0.45) 0%, transparent 45%);
    border-radius: 50%;
}
.floating-create::after {
    content: '';
    position: absolute; inset: -4px;
    border-radius: 50%;
    border: 2px solid color-mix(in oklab, var(--primary) 50%, transparent);
    animation: fabPulse 2.6s ease-in-out infinite;
    pointer-events: none;
}
@keyframes fabPulse {
    0%,100% { transform: scale(1);    opacity: 0.6; }
    50%     { transform: scale(1.20); opacity: 0;   }
}
.floating-create:hover {
    transform: scale(1.08) rotate(90deg);
    box-shadow:
        0 1px 0 rgba(255,255,255,0.4) inset,
        0 26px 60px -8px color-mix(in oklab, var(--primary) 65%, transparent),
        0 12px 28px -6px color-mix(in oklab, var(--primary-2) 50%, transparent);
}
.floating-create:active {
    transform: scale(0.95) rotate(90deg);
    transition-duration: 0.12s;
}

/* =========================================================
   MODAL — clear glass with strong text
   ========================================================= */
.modal-content {
    border: 1px solid rgba(255,255,255,0.32) !important;
    border-radius: 36px !important;
    background: linear-gradient(155deg,
        rgba(255,255,255,0.14) 0%,
        rgba(255,255,255,0.05) 45%,
        rgba(214,232,255,0.07) 100%) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-xl);
    backdrop-filter: var(--backdrop-glass-xl);
    color: var(--text-on-glass-light) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.60) inset,
        0 30px 80px -20px rgba(15,23,42,0.30),
        0 12px 32px -8px rgba(15,23,42,0.14) !important;
    overflow: hidden;
    position: relative;
}
.modal-content::before {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(140deg,
        rgba(255,255,255,0.32) 0%, transparent 35%);
    pointer-events: none;
    z-index: 1;
    border-radius: inherit;
}
.modal-content > * { position: relative; z-index: 2; }

.modal-title,
.modal-body,
.modal-body p,
.modal-body span,
.modal-body label,
.modal-footer {
    color: var(--text-on-glass-light) !important;
}

.modal.fade .modal-dialog {
    transform: translate3d(0,24px,0) scale(0.94);
    opacity: 0;
    transition:
        transform 0.4s var(--ease-glass),
        opacity   0.4s var(--ease-glass);
}
.modal.show .modal-dialog {
    transform: translate3d(0,0,0) scale(1);
    opacity: 1;
}

.modal-backdrop {
    -webkit-backdrop-filter: blur(10px) saturate(150%);
    backdrop-filter: blur(10px) saturate(150%);
    background: rgba(8,15,30,0.32) !important;
}

/* =========================================================
   FORM INPUTS
   ========================================================= */
.form-control,
.form-select {
    border: 1px solid rgba(255,255,255,0.40) !important;
    border-radius: 16px !important;
    background: rgba(255,255,255,0.26) !important;
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    backdrop-filter: blur(20px) saturate(180%);
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.55),
        inset 0 -1px 0 rgba(0,0,0,0.03),
        0 3px 10px -2px rgba(15,23,42,0.05);
    color: var(--text-on-glass-light) !important;
    transition:
        background 0.3s var(--ease-smooth),
        box-shadow 0.3s var(--ease-smooth),
        transform  0.3s var(--ease-spring);
}
.form-control::placeholder,
.form-select::placeholder { color: rgba(74,84,112,0.55); }

.form-control:focus,
.form-select:focus {
    background: rgba(255,255,255,0.55) !important;
    transform: translate3d(0,-1px,0);
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.75),
        0 0 0 3px color-mix(in oklab, var(--primary) 22%, transparent),
        0 8px 22px -4px color-mix(in oklab, var(--primary) 28%, transparent) !important;
    outline: none;
    border-color: color-mix(in oklab, var(--primary) 50%, transparent) !important;
}

textarea.form-control { resize: none; line-height: 1.7; }

.form-label,
label { color: var(--text-on-glass-light) !important; }

/* =========================================================
   AVATAR
   ========================================================= */
.nav-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.78);
    box-shadow:
        0 4px 14px -2px rgba(15,23,42,0.14),
        inset 0 1px 0 rgba(255,255,255,0.5);
    transition:
        transform 0.4s var(--ease-spring),
        box-shadow 0.35s var(--ease-smooth);
}
.nav-avatar:hover {
    transform: scale(1.10) rotate(6deg);
    box-shadow:
        0 10px 28px -6px color-mix(in oklab, var(--primary) 35%, transparent),
        inset 0 1px 0 rgba(255,255,255,0.65);
}

/* =========================================================
   COLOR BUTTON
   ========================================================= */
.color-btn {
    width: 28px; height: 28px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.75);
    cursor: pointer;
    transition:
        transform 0.3s var(--ease-spring),
        box-shadow 0.28s var(--ease-smooth),
        border-color 0.25s;
    box-shadow: 0 3px 10px -2px rgba(15,23,42,0.14);
}
.color-btn:hover {
    transform: scale(1.20) rotate(10deg);
    border-color: rgba(255,255,255,0.95);
    box-shadow: 0 6px 18px -4px rgba(15,23,42,0.20);
}
.color-btn:active { transform: scale(1.04); transition-duration: 0.10s; }

/* =========================================================
   BADGE
   ========================================================= */
.badge {
    border-radius: 999px;
    padding: 6px 14px;
    font-weight: 600;
    font-size: 0.72rem;
    letter-spacing: 0.2px;
    -webkit-backdrop-filter: blur(10px) saturate(170%);
    backdrop-filter: blur(10px) saturate(170%);
    border: 1px solid rgba(255,255,255,0.30);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.36);
    transition: transform 0.22s var(--ease-spring);
}
.badge:hover { transform: scale(1.06); }
.note-badges { font-size: 0.73rem; opacity: 0.95; transition: opacity 0.25s; }

/* =========================================================
   SHARED BANNER
   ========================================================= */
.shared-banner {
    border-left: 3px solid color-mix(in oklab, var(--accent-aqua) 75%, transparent);
    background: linear-gradient(90deg,
        color-mix(in oklab, var(--accent-aqua) 10%, transparent),
        transparent);
    color: var(--text-on-glass-light) !important;
}

/* =========================================================
   ENTRANCE ANIMATIONS
   ========================================================= */
@keyframes cardPop {
    from { opacity: 0; transform: translate3d(0,18px,0) scale(0.96); }
    to   { opacity: 1; transform: translate3d(0,0,0) scale(1); }
}
.note-grid-view .note-card,
.note-list-view .note-card {
    animation-delay: calc(var(--i, 0) * 50ms);
}
.note-card:nth-child(1)  { --i: 0; }
.note-card:nth-child(2)  { --i: 1; }
.note-card:nth-child(3)  { --i: 2; }
.note-card:nth-child(4)  { --i: 3; }
.note-card:nth-child(5)  { --i: 4; }
.note-card:nth-child(6)  { --i: 5; }
.note-card:nth-child(7)  { --i: 6; }
.note-card:nth-child(8)  { --i: 7; }
.note-card:nth-child(9)  { --i: 8; }
.note-card:nth-child(10) { --i: 9; }
.note-card:nth-child(n+11) { --i: 10; }

.note-list-view .note-card { animation-name: listSlide; }
@keyframes listSlide {
    from { opacity: 0; transform: translate3d(-28px,0,0); }
    to   { opacity: 1; transform: translate3d(0,0,0); }
}
.note-grid-view, .note-list-view {
    animation: viewFade 0.36s var(--ease-smooth);
}
@keyframes viewFade {
    from { opacity: 0.4; } to { opacity: 1; }
}

/* =========================================================
   SCROLL REVEAL
   ========================================================= */
.scroll-reveal {
    opacity: 0;
    transform: translate3d(0,24px,0);
    transition:
        opacity 0.6s var(--ease-glass),
        transform 0.6s var(--ease-glass);
}
.scroll-reveal.visible {
    opacity: 1;
    transform: translate3d(0,0,0);
}

/* =========================================================
   SKELETON
   ========================================================= */
.skeleton {
    background: linear-gradient(90deg,
        rgba(255,255,255,0.14) 25%,
        rgba(255,255,255,0.40) 50%,
        rgba(255,255,255,0.14) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s linear infinite;
    border-radius: 12px;
}
@keyframes shimmer {
    from { background-position: 200% 0; }
    to   { background-position: -200% 0; }
}

/* =========================================================
   TOAST — FIX text contrast (the bug in screenshot)
   ========================================================= */
.toast {
    border-radius: 22px !important;
    background: rgba(255,255,255,0.32) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-lg) !important;
    backdrop-filter: var(--backdrop-glass-lg) !important;
    border: 1px solid rgba(255,255,255,0.45) !important;
    color: var(--text-on-glass-light) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.65) inset,
        0 16px 40px -10px rgba(15,23,42,0.20) !important;
    animation: toastIn 0.4s var(--ease-spring) both;
}
.toast .toast-body,
.toast .toast-header,
.toast strong,
.toast span,
.toast div,
.toast p {
    color: var(--text-on-glass-light) !important;
}
.toast .btn-close {
    filter: none;
    opacity: 0.75;
}
@keyframes toastIn {
    from { opacity: 0; transform: translate3d(0,16px,0) scale(0.94); }
    to   { opacity: 1; transform: translate3d(0,0,0) scale(1); }
}

/* =========================================================
   DROPDOWN
   ========================================================= */
.dropdown-menu {
    border: 1px solid rgba(255,255,255,0.38) !important;
    border-radius: 20px !important;
    background: rgba(248,252,255,0.42) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-lg);
    backdrop-filter: var(--backdrop-glass-lg);
    color: var(--text-on-glass-light) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.60) inset,
        0 18px 50px -10px rgba(15,23,42,0.20);
    padding: 8px !important;
    animation: dropIn 0.28s var(--ease-glass) both;
    transform-origin: top center;
}
@keyframes dropIn {
    from { opacity: 0; transform: translate3d(0,-8px,0) scale(0.96); }
    to   { opacity: 1; transform: translate3d(0,0,0) scale(1); }
}
.dropdown-item {
    border-radius: 12px !important;
    padding: 10px 16px !important;
    color: var(--text-on-glass-light) !important;
    transition:
        background 0.22s,
        transform  0.22s var(--ease-spring),
        color 0.22s;
    font-size: 0.92rem;
}
.dropdown-item:hover {
    background: color-mix(in oklab, var(--primary) 14%, transparent) !important;
    color: var(--text-on-glass-light) !important;
    transform: translateX(3px);
}

/* =========================================================
   SCROLLBAR
   ========================================================= */
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.10);
    border-radius: 999px;
}
::-webkit-scrollbar-thumb {
    background: color-mix(in oklab, var(--primary) 38%, transparent);
    border-radius: 999px;
    border: 2px solid transparent;
    background-clip: padding-box;
    transition: background 0.25s;
}
::-webkit-scrollbar-thumb:hover {
    background: color-mix(in oklab, var(--primary) 60%, transparent);
    background-clip: padding-box;
}

/* =========================================================
   SELECTION
   ========================================================= */
::selection {
    background: color-mix(in oklab, var(--primary) 32%, transparent);
    color: var(--text-on-glass-light);
}

/* =========================================================
   ====================================================
   DARK MODE — invert text contrast
   ====================================================
   ========================================================= */
body[data-bs-theme="dark"],
[data-bs-theme="dark"] body {
    color: var(--text-on-glass-dark);
    background-color: #04081A;
    background-image:
        radial-gradient(ellipse 70% 55% at 15% 10%,
            color-mix(in oklab, var(--primary) 22%, transparent) 0%,
            transparent 58%),
        radial-gradient(ellipse 60% 50% at 85% 18%,
            color-mix(in oklab, var(--primary-2) 24%, transparent) 0%,
            transparent 55%),
        radial-gradient(ellipse 65% 50% at 50% 92%,
            color-mix(in oklab, var(--primary-3) 14%, transparent) 0%,
            transparent 55%),
        linear-gradient(160deg,
            #050B1C 0%, #08122A 45%, #0A1428 100%);
}

body[data-bs-theme="dark"]::before,
[data-bs-theme="dark"] body::before {
    background: radial-gradient(circle,
        color-mix(in oklab, var(--primary) 35%, transparent) 0%,
        color-mix(in oklab, var(--primary-2) 22%, transparent) 50%,
        transparent 72%);
}
body[data-bs-theme="dark"]::after,
[data-bs-theme="dark"] body::after {
    background: radial-gradient(circle,
        color-mix(in oklab, var(--primary-2) 32%, transparent) 0%,
        color-mix(in oklab, var(--primary-3) 18%, transparent) 50%,
        transparent 72%);
}

/* dark navbar */
body[data-bs-theme="dark"] .navbar,
[data-bs-theme="dark"] .navbar {
    background: linear-gradient(145deg,
        rgba(255,255,255,0.08) 0%,
        rgba(255,255,255,0.03) 50%,
        rgba(180,200,255,0.05) 100%) !important;
    border-color: rgba(255,255,255,0.14) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-dark-lg);
    backdrop-filter: var(--backdrop-glass-dark-lg);
    color: var(--text-on-glass-dark) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 12px 36px -8px rgba(0,0,0,0.45) !important;
}
body[data-bs-theme="dark"] .navbar-brand,
[data-bs-theme="dark"] .navbar-brand {
    color: var(--text-on-glass-dark) !important;
}
body[data-bs-theme="dark"] .navbar .small,
body[data-bs-theme="dark"] .navbar .nav-link,
body[data-bs-theme="dark"] .navbar span,
[data-bs-theme="dark"] .navbar .small,
[data-bs-theme="dark"] .navbar .nav-link,
[data-bs-theme="dark"] .navbar span {
    color: var(--text-on-glass-dark-soft) !important;
}

/* dark glass surfaces */
body[data-bs-theme="dark"] .glass,
body[data-bs-theme="dark"] .bg-body-tertiary,
[data-bs-theme="dark"] .glass,
[data-bs-theme="dark"] .bg-body-tertiary {
    background: var(--glass-bg-dark) !important;
    border-color: var(--glass-border-dark) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-dark);
    backdrop-filter: var(--backdrop-glass-dark);
    color: var(--text-on-glass-dark) !important;
    box-shadow: var(--glass-shadow-dark) !important;
}

/* dark note card */
body[data-bs-theme="dark"] .note-card,
[data-bs-theme="dark"] .note-card {
    background: linear-gradient(155deg,
        rgba(255,255,255,0.08) 0%,
        rgba(255,255,255,0.03) 45%,
        rgba(180,200,255,0.06) 100%) !important;
    border-color: rgba(255,255,255,0.14) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-dark);
    backdrop-filter: var(--backdrop-glass-dark);
    color: var(--text-on-glass-dark) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 12px 36px -8px rgba(0,0,0,0.42),
        0 30px 60px -22px rgba(0,0,0,0.36) !important;
}
body[data-bs-theme="dark"] .note-card:hover,
[data-bs-theme="dark"] .note-card:hover {
    box-shadow:
        0 1px 0 rgba(255,255,255,0.14) inset,
        0 24px 56px -12px rgba(0,0,0,0.55),
        0 0 0 1px color-mix(in oklab, var(--primary) 22%, transparent),
        0 0 40px -8px color-mix(in oklab, var(--primary) 22%, transparent) !important;
    border-color: rgba(255,255,255,0.22) !important;
}

body[data-bs-theme="dark"] .card-title,
[data-bs-theme="dark"] .card-title { color: var(--text-on-glass-dark) !important; }

body[data-bs-theme="dark"] .card-text,
[data-bs-theme="dark"] .card-text { color: var(--text-on-glass-dark-soft) !important; }

/* dark modal */
body[data-bs-theme="dark"] .modal-content,
[data-bs-theme="dark"] .modal-content {
    background: linear-gradient(155deg,
        rgba(255,255,255,0.10) 0%,
        rgba(255,255,255,0.04) 50%,
        rgba(180,200,255,0.06) 100%) !important;
    border-color: rgba(255,255,255,0.16) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-dark-xl);
    backdrop-filter: var(--backdrop-glass-dark-xl);
    color: var(--text-on-glass-dark) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.12) inset,
        0 30px 80px -20px rgba(0,0,0,0.6) !important;
}
body[data-bs-theme="dark"] .modal-title,
body[data-bs-theme="dark"] .modal-body,
body[data-bs-theme="dark"] .modal-body p,
body[data-bs-theme="dark"] .modal-body span,
body[data-bs-theme="dark"] .modal-body label,
body[data-bs-theme="dark"] .modal-footer,
[data-bs-theme="dark"] .modal-title,
[data-bs-theme="dark"] .modal-body,
[data-bs-theme="dark"] .modal-body p,
[data-bs-theme="dark"] .modal-body span,
[data-bs-theme="dark"] .modal-body label,
[data-bs-theme="dark"] .modal-footer {
    color: var(--text-on-glass-dark) !important;
}

/* dark inputs */
body[data-bs-theme="dark"] #searchInput,
body[data-bs-theme="dark"] .form-control,
body[data-bs-theme="dark"] .form-select,
[data-bs-theme="dark"] #searchInput,
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select {
    background: rgba(255,255,255,0.06) !important;
    border-color: rgba(255,255,255,0.14) !important;
    color: var(--text-on-glass-dark) !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.08),
        0 3px 10px -2px rgba(0,0,0,0.25) !important;
}
body[data-bs-theme="dark"] .form-control::placeholder,
body[data-bs-theme="dark"] .form-select::placeholder,
body[data-bs-theme="dark"] #searchInput::placeholder,
[data-bs-theme="dark"] .form-control::placeholder,
[data-bs-theme="dark"] .form-select::placeholder,
[data-bs-theme="dark"] #searchInput::placeholder {
    color: rgba(155,170,203,0.55);
}
body[data-bs-theme="dark"] .form-control:focus,
body[data-bs-theme="dark"] .form-select:focus,
body[data-bs-theme="dark"] #searchInput:focus,
[data-bs-theme="dark"] .form-control:focus,
[data-bs-theme="dark"] .form-select:focus,
[data-bs-theme="dark"] #searchInput:focus {
    background: rgba(255,255,255,0.12) !important;
    box-shadow:
        0 0 0 3px color-mix(in oklab, var(--primary) 26%, transparent),
        inset 0 1px 0 rgba(255,255,255,0.14) !important;
}

body[data-bs-theme="dark"] .form-label,
body[data-bs-theme="dark"] label,
[data-bs-theme="dark"] .form-label,
[data-bs-theme="dark"] label {
    color: var(--text-on-glass-dark) !important;
}

/* dark buttons */
body[data-bs-theme="dark"] .btn-outline-secondary,
body[data-bs-theme="dark"] .btn-light,
[data-bs-theme="dark"] .btn-outline-secondary,
[data-bs-theme="dark"] .btn-light {
    background: rgba(255,255,255,0.07) !important;
    border-color: rgba(255,255,255,0.16) !important;
    color: var(--text-on-glass-dark) !important;
}

/* dark toast */
body[data-bs-theme="dark"] .toast,
[data-bs-theme="dark"] .toast {
    background: rgba(255,255,255,0.08) !important;
    border-color: rgba(255,255,255,0.14) !important;
    color: var(--text-on-glass-dark) !important;
    -webkit-backdrop-filter: var(--backdrop-glass-dark-lg) !important;
    backdrop-filter: var(--backdrop-glass-dark-lg) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.10) inset,
        0 16px 40px -10px rgba(0,0,0,0.5) !important;
}
body[data-bs-theme="dark"] .toast .toast-body,
body[data-bs-theme="dark"] .toast .toast-header,
body[data-bs-theme="dark"] .toast strong,
body[data-bs-theme="dark"] .toast span,
body[data-bs-theme="dark"] .toast div,
body[data-bs-theme="dark"] .toast p,
[data-bs-theme="dark"] .toast .toast-body,
[data-bs-theme="dark"] .toast .toast-header,
[data-bs-theme="dark"] .toast strong,
[data-bs-theme="dark"] .toast span,
[data-bs-theme="dark"] .toast div,
[data-bs-theme="dark"] .toast p {
    color: var(--text-on-glass-dark) !important;
}
body[data-bs-theme="dark"] .toast .btn-close,
[data-bs-theme="dark"] .toast .btn-close {
    filter: invert(1) brightness(1.6);
    opacity: 0.8;
}

/* dark scrollbar */
body[data-bs-theme="dark"] ::-webkit-scrollbar-track,
[data-bs-theme="dark"] ::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
body[data-bs-theme="dark"] ::-webkit-scrollbar-thumb,
[data-bs-theme="dark"] ::-webkit-scrollbar-thumb {
    background: color-mix(in oklab, var(--primary) 30%, transparent);
}

body[data-bs-theme="dark"] .badge,
[data-bs-theme="dark"] .badge { border-color: rgba(255,255,255,0.16); }

body[data-bs-theme="dark"] .dropdown-menu,
[data-bs-theme="dark"] .dropdown-menu {
    background: rgba(20,32,68,0.45) !important;
    border-color: rgba(255,255,255,0.14) !important;
    color: var(--text-on-glass-dark) !important;
    box-shadow:
        0 1px 0 rgba(255,255,255,0.08) inset,
        0 20px 60px -10px rgba(0,0,0,0.5) !important;
}
body[data-bs-theme="dark"] .dropdown-item,
[data-bs-theme="dark"] .dropdown-item { color: var(--text-on-glass-dark) !important; }
body[data-bs-theme="dark"] .dropdown-item:hover,
[data-bs-theme="dark"] .dropdown-item:hover {
    background: color-mix(in oklab, var(--primary) 18%, transparent) !important;
    color: var(--text-on-glass-dark) !important;
}

body[data-bs-theme="dark"] ::selection,
[data-bs-theme="dark"] ::selection {
    background: color-mix(in oklab, var(--primary) 36%, transparent);
    color: var(--text-on-glass-dark);
}

body[data-bs-theme="dark"] .shared-banner,
[data-bs-theme="dark"] .shared-banner {
    border-left-color: color-mix(in oklab, var(--accent-aqua) 55%, transparent);
    background: linear-gradient(90deg,
        color-mix(in oklab, var(--accent-aqua) 9%, transparent),
        transparent);
    color: var(--text-on-glass-dark) !important;
}

/* Auto dark mode (system preference, no data-bs-theme set) */
@media (prefers-color-scheme: dark) {
    body:not([data-bs-theme="light"]) {
        color: var(--text-on-glass-dark);
    }
}

/* =========================================================
   PERFORMANCE
   ========================================================= */
@media (max-width: 480px) {
    :root {
        --backdrop-glass:    blur(18px) saturate(180%) brightness(1.06);
        --backdrop-glass-lg: blur(26px) saturate(190%) brightness(1.08);
        --backdrop-glass-xl: blur(34px) saturate(200%) brightness(1.10);
    }
    body::before, body::after { display: none; }
}

@media (prefers-reduced-transparency: reduce) {
    .glass, .navbar, .note-card, .modal-content, .toast, .dropdown-menu,
    .bg-body-tertiary, .form-control, .form-select, #searchInput {
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
        background: rgba(255,255,255,0.92) !important;
    }
}

/* =========================================================
   REDUCED MOTION
   ========================================================= */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.10ms !important;
    }
    body::before, body::after { animation: none !important; }
}

/* =========================================================
   GLOBAL FONT & NOTE COLOR SYSTEM (preserved)
   ========================================================= */
body, .note-card, .modal-content, .form-control, .btn,
h1, h2, h3, h4, h5, h6, p, span, div {
    font-size: inherit !important;
}

.note-card {
    background-color: var(--note-default-color);
    transition: background-color 0.3s ease;
}

.note-card[style*="background-color"],
#modalContentWrapper[style*="background-color"] {
    background-color: var(--note-individual-color, var(--note-default-color)) !important;
}

#modalContentWrapper {
    background-color: var(--note-individual-color, var(--note-default-color)) !important;
}

/* =========================================================
   AUTO-CONTRAST — text adapts to inline note color
   When user picks a (light/pastel) bg color via inline style,
   force near-black text so content stays readable in BOTH modes,
   especially dark mode where default text would be white.
   ========================================================= */
.note-card[style*="background"],
.note-card[style*="background"] .card-title,
.note-card[style*="background"] .card-text,
.note-card[style*="background"] p,
.note-card[style*="background"] span,
.note-card[style*="background"] small,
.modal-content[style*="background"],
.modal-content[style*="background"] .modal-title,
.modal-content[style*="background"] .modal-body,
.modal-content[style*="background"] .modal-body * ,
.modal-content[style*="background"] .form-control,
.modal-content[style*="background"] .form-select,
.modal-content[style*="background"] label {
    color: #0A1024 !important;
}
.note-card[style*="background"] .card-text,
.modal-content[style*="background"] .form-control::placeholder,
.modal-content[style*="background"] .form-select::placeholder {
    color: rgba(10,16,36,0.65) !important;
}

/* Inputs inside a colored modal: stay readable on tinted bg */
.modal-content[style*="background"] .form-control,
.modal-content[style*="background"] .form-select,
.modal-content[style*="background"] textarea {
    background: rgba(255,255,255,0.45) !important;
    border-color: rgba(10,16,36,0.12) !important;
}

/* Close button inside colored modal */
.modal-content[style*="background"] .btn-close {
    filter: none !important;
    opacity: 0.75;
}
//----//

app.js
// ====================== BIẾN TOÀN CỤC ======================
// window.APP_CONFIG được inject từ index.php
const currentUserId   = window.APP_CONFIG?.userId   ?? 0;
const currentUserName = window.APP_CONFIG?.userName  ?? 'User';

let typingTimer, searchTimer;
let currentLabelId      = null;
let currentViewMode     = 'my_notes';
let currentPermission   = 'owner';
let isLockedState       = false;
let currentNoteId       = null;
let passwordModalInstance = null;
let tempOpenData        = null;
let autoRefreshInterval = null;
let autoSaveTimer          = null;
let autoSaveRetryTimer     = null;
let autoSaveBusyTimer      = null;
let autoSaveInFlightSeq    = 0;
let lastAutoSavePersistSig = '';
let isSaving               = false;

// Bootstrap modals (khởi tạo sau DOM ready)
let noteModal           = null;
let customAlertModal    = null;
let customConfirmModal  = null;

function appendCsrfToken(formData) {
    formData.append('csrf_token', window.APP_CONFIG?.csrf_token || '');
}

function getNoteContentVersion() {
    const contentEl = document.getElementById('noteContent');
    return parseInt(contentEl?.dataset.version, 10) || 1;
}

function buildWsNoteUpdatePayload() {
    const titleEl = document.getElementById('noteTitle');
    const contentEl = document.getElementById('noteContent');
    return {
        type:      'update',
        note_id:   currentNoteIdForWS,
        title:     (titleEl && titleEl.value) || '',
        content:   (contentEl && contentEl.value) || '',
        version:   getNoteContentVersion(),
        user_name: currentUserName
    };
}

function setNoteOwnerToolbarVisible(visible) {
    const display = visible ? 'block' : 'none';
    ['toolsSection', 'colorSection', 'shareManagerSection', 'btnTrashNote'].forEach((id) => {
        const node = document.getElementById(id);
        if (node) node.style.display = display;
    });
}

// ====================== DOM READY (một handler duy nhất) ======================
document.addEventListener('DOMContentLoaded', () => {
    // --- Modals Bootstrap ---
    noteModal          = new bootstrap.Modal(document.getElementById('noteModal'));
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    customAlertModal   = new bootstrap.Modal(document.getElementById('customAlertModal'));
    customConfirmModal = new bootstrap.Modal(document.getElementById('customConfirmModal'));

    // --- Khởi tạo view ---
    setViewMode('my_notes');

    // --- IndexedDB ---
    initIndexedDB();

    // --- Offline / Online events ---
    window.addEventListener('online', () => {
        showToast('Đã kết nối lại. Đang đồng bộ...', 'info');
        setTimeout(syncOfflineNotes, 1000);
    });

    if (!navigator.onLine) {
        setTimeout(loadNotesOfflineFallback, 800);
    }

    // --- Theme preview ---
    const themeSelect = document.getElementById('settingTheme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function () {
            applyTheme(this.value);
        });
    }

    // --- Font size preview ---
    const fontSelect = document.getElementById('settingFontSize');
    if (fontSelect) {
        fontSelect.addEventListener('change', function () {
            document.body.style.fontSize = this.value;
        });
    }

    // --- Force sync content khi blur khỏi noteContent ---
    const noteContentEl = document.getElementById('noteContent');
    if (noteContentEl) {
        noteContentEl.addEventListener('blur', function () {
            if (wsReady && currentNoteIdForWS) {
                _wsSendEditorStateIfChanged();
            }
        });
    }

    // --- Bảo vệ modal không đóng khi đang gõ ---
    const noteModalEl = document.getElementById('noteModal');
    if (noteModalEl) {
        noteModalEl.addEventListener('hide.bs.modal', function (event) {
            if (document.getElementById('noteId').value &&
                (document.getElementById('noteTitle')   === document.activeElement ||
                 document.getElementById('noteContent') === document.activeElement)) {
                event.preventDefault();
            }
        });
    }
});

// ====================== REALTIME TYPING BROADCAST (80ms debounce) ======================
let realtimeTypingTimer = null;
document.addEventListener('input', function (e) {
    if (window.__remoteUpdating) return;
    if (!wsReady || !currentNoteIdForWS) return;
    if (e.target.id !== 'noteTitle' && e.target.id !== 'noteContent') return;

    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = setTimeout(() => {
        _wsSendEditorStateIfChanged();
    }, 80);
});

// ====================== VIEW MODE ======================
function setViewMode(mode) {
    const container = document.getElementById('notesContainer');
    container.style.transition  = 'all .25s ease';
    container.style.opacity     = '0';
    container.style.transform   = 'translateY(10px) scale(.98)';

    setTimeout(() => {
        currentViewMode = mode;
        currentLabelId  = null;

        document.getElementById('btnViewShared').style.display  = mode === 'shared'   ? 'none' : 'block';
        document.getElementById('btnViewTrash').style.display   = mode === 'trash'    ? 'none' : 'block';
        document.getElementById('btnViewMyNotes').style.display = mode === 'my_notes' ? 'none' : 'block';

        const viewTitle     = document.getElementById('viewTitle');
        const btnCreate     = document.getElementById('btnCreateNote');
        const addLabelGroup = document.getElementById('addLabelGroup');

        if (mode === 'my_notes') {
            viewTitle.style.display     = 'none';
            btnCreate.style.display     = 'block';
            addLabelGroup.style.display = 'flex';
        } else if (mode === 'trash') {
            viewTitle.innerHTML     = '🗑️ THÙNG RÁC';
            viewTitle.style.display = 'block';
            viewTitle.className     = 'text-danger fw-bold m-0 align-self-center';
            btnCreate.style.display     = 'none';
            addLabelGroup.style.display = 'none';
        } else if (mode === 'shared') {
            viewTitle.innerHTML     = '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';
            viewTitle.style.display = 'block';
            viewTitle.className     = 'text-info fw-bold m-0 align-self-center';
            btnCreate.style.display     = 'none';
            addLabelGroup.style.display = 'none';
        }

        loadFilterLabels(() => {
            liveSearch();
            setTimeout(() => {
                container.style.opacity   = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });

        startAutoRefresh();
    }, 180);
}

// ====================== SEARCH ======================
async function liveSearch() {
    clearTimeout(searchTimer);

    searchTimer = setTimeout(async () => {
        if (!navigator.onLine) {
            const hasOffline = await loadNotesOfflineFallback();
            if (hasOffline) return;
        }

        const q   = encodeURIComponent(document.getElementById('searchInput').value);
        let url   = `api/search.php?q=${q}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;

        fetch(url)
            .then(res => res.json())
            .then(renderNotes)
            .catch(async () => {
                await loadNotesOfflineFallback();
            });
    }, 300);
}

// ====================== RENDER NOTES ======================
function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = {
            trash:    'Thùng rác trống.',
            shared:   'Chưa có ghi chú nào được chia sẻ.',
            my_notes: 'Chưa có ghi chú nào.'
        };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass   = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor    = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName  = n.owner_name || '';
        const permission = n.permission || 'owner';

        let icons = '';
        if (n.is_locked == 1) icons += '<i class="bi bi-lock-fill text-warning me-1" title="Đã khóa"></i>';
        if (ownerName)         icons += '<i class="bi bi-people-fill text-info me-1" title="Được chia sẻ"></i>';
        if (n.is_pinned == 1)  icons += '<i class="bi bi-pin-fill text-danger me-1" title="Đã ghim"></i>';

        const shareInfo = ownerName ? `
            <div class="position-absolute bottom-0 start-0 end-0 px-3 pb-2 d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(ownerName)}</small>
                ${permission === 'edit'
                    ? `<span class="badge bg-success ms-1">✏️ Edit</span>`
                    : `<span class="badge bg-secondary ms-1">👁️ View</span>`}
            </div>` : '';

        const card = document.createElement('div');
        card.className     = 'card note-card';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className        = 'card-body position-relative pb-4';
        body.dataset.id         = n.id;
        body.dataset.title      = n.title      || '';
        body.dataset.content    = n.content    || '';
        body.dataset.isLocked   = n.is_locked  || 0;
        body.dataset.color      = n.color      || '';
        body.dataset.permission = permission;
        body.dataset.ownerName  = ownerName;

        body.addEventListener('click', () => handleNoteOpen(
            parseInt(body.dataset.id),
            body.dataset.title,
            body.dataset.content,
            parseInt(body.dataset.isLocked),
            body.dataset.color,
            body.dataset.permission,
            body.dataset.ownerName
        ));

        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                     onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                     <i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1">
                ${icons} ${escapeHtml(n.title) || 'Không tiêu đề'}
            </h5>
            <p class="card-text text-muted text-truncate"
               style="white-space:pre-wrap; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;">
               ${escapeHtml(n.content) || 'Không có nội dung...'}
            </p>
            ${shareInfo}
        `;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    currentNoteId     = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        document.getElementById('passwordModalTitle').textContent = '🔒 Ghi chú đã bị khóa';
        document.getElementById('notePasswordInput').value        = '';
        document.getElementById('passwordError').style.display    = 'none';
        window.tempOpenData = { id, title, content, color, permission, ownerName };
        passwordModalInstance.show();
        setTimeout(() => document.getElementById('notePasswordInput').focus(), 500);
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    const password = document.getElementById('notePasswordInput').value.trim();
    const errorEl  = document.getElementById('passwordError');
    if (!password) {
        errorEl.textContent   = 'Vui lòng nhập mật khẩu!';
        errorEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append('note_id',  currentNoteId);
    fd.append('password', password);

    fetch('api/verify_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                passwordModalInstance.hide();
                isLockedState = true;
                openNoteModal(
                    window.tempOpenData.id,
                    d.title, d.content, d.color,
                    d.permission,
                    window.tempOpenData.ownerName
                );
            } else {
                errorEl.textContent   = d.message || 'Mật khẩu không đúng!';
                errorEl.style.display = 'block';
                document.getElementById('notePasswordInput').value = '';
                document.getElementById('notePasswordInput').focus();
            }
        });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    currentNoteId     = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl           = document.getElementById('noteContent');
    contentEl.dataset.version = '1';

    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText            = '';
    lastAutoSavePersistSig = '';
    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    clearTimeout(realtimeTypingTimer);
    autoSaveTimer          = null;
    autoSaveRetryTimer     = null;
    autoSaveBusyTimer      = null;
    realtimeTypingTimer    = null;
    autoSaveInFlightSeq++;

    // --- Áp dụng màu ghi chú ---
    const modalWrapper  = document.getElementById('modalContentWrapper');
    const resolvedColor = (color && color.trim() !== '')
        ? color
        : (document.documentElement.style.getPropertyValue('--note-default-color') || '#ffffff');
    modalWrapper.style.backgroundColor = resolvedColor;
    modalWrapper.style.setProperty('--note-individual-color', resolvedColor);

    // --- Ẩn tất cả các section trước ---
    ['toolsSection','colorSection','shareManagerSection','btnTrashNote','btnRestoreNote','btnDeletePermanent']
        .forEach(el => { const e = document.getElementById(el); if (e) e.style.display = 'none'; });

    const isTrash  = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice   = document.getElementById('sharedNotice');
    const wsBadge  = document.getElementById('wsStatusBadge');

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display     = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
        if (wsBadge) wsBadge.style.display = 'none';

    } else if (isShared) {
        notice.style.display = 'block';
        notice.innerHTML = `
            <strong>Được chia sẻ bởi:</strong> <b>${escapeHtml(ownerName)}</b><br>
            <strong>Quyền:</strong> <b>${permission === 'edit' ? '✅ Có thể chỉnh sửa' : '👁️ Chỉ xem'}</b>
        `;
        document.getElementById('noteTitle').readOnly   = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';
        if (id) {
            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(r => r.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
        }
        if (wsBadge) wsBadge.style.display = permission === 'edit' ? 'inline-flex' : 'none';

    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = false;
        document.getElementById('noteContent').readOnly = false;

        if (id) {
            setNoteOwnerToolbarVisible(true);

            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(r => r.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
            if (wsBadge) wsBadge.style.display = 'inline-flex';
        }
    }

    // --- Lấy version mới nhất từ server ---
    if (id) {
        fetch(`api/get_notes.php?note_id=${id}`)
            .then(r => r.json())
            .then(note => { if (note && note.version) contentEl.dataset.version = note.version; })
            .catch(() => {});
    }

    // --- Placeholder cho note mới ---
    if (!id) {
        document.getElementById('noteTitle').placeholder   = 'Nhập tiêu đề ghi chú...';
        document.getElementById('noteContent').placeholder = 'Nhập nội dung ghi chú của bạn...';
    }

    // --- Khởi động realtime (một lần duy nhất) ---
    if (id && (permission === 'edit' || permission === 'owner')) {
        startRealtimeForNote(id, permission);
    }

    noteModal.show();
}

// ====================== AUTO REFRESH ======================
function startAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    if (currentViewMode === 'shared' && currentPermission === 'edit') {
        autoRefreshInterval = setInterval(liveSearch, 4000);
    }
}

function closeAndReload() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    stopRealtime();
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
// Tích hợp: offline fallback + WebSocket broadcast + xử lý conflict
// =========================================================
function _autoSavePayloadSignature() {
    const noteId  = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;
    return `${noteId}\x1e${title}\x1e${content}\x1e${getNoteContentVersion()}`;
}

function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    if (window.__remoteUpdating) return;

    const noteId  = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;

    if (!noteId && !title && !content) return;

    document.getElementById('saveStatus').innerHTML = '<i class="bi bi-hourglass-split"></i> Đang lưu...';

    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    autoSaveRetryTimer = null;
    autoSaveBusyTimer  = null;

    autoSaveTimer = setTimeout(() => {
        autoSaveTimer = null;

        const runCommitted = () => {
            if (isSaving) {
                clearTimeout(autoSaveBusyTimer);
                autoSaveBusyTimer = setTimeout(runCommitted, 150);
                return;
            }
            autoSaveBusyTimer = null;

            const nid  = document.getElementById('noteId').value;
            const tit  = document.getElementById('noteTitle').value.trim();
            const cont = document.getElementById('noteContent').value;

            if (!nid && !tit && !cont) return;

            if (nid && _autoSavePayloadSignature() === lastAutoSavePersistSig) {
                const statusEl = document.getElementById('saveStatus');
                statusEl.innerHTML = lastAutoSavePersistSig
                    ? '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu'
                    : '';
                return;
            }

            // --- OFFLINE: lưu cục bộ ngay lập tức ---
            if (!navigator.onLine) {
                const noteData = {
                    id:         nid || 'temp_' + Date.now(),
                    title:      tit,
                    content:    cont,
                    version:    getNoteContentVersion(),
                    updated_at: new Date().toISOString()
                };
                saveNoteOffline(noteData);
                document.getElementById('saveStatus').innerHTML =
                    '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Đã lưu offline</span>';
                showToast('Không có mạng. Ghi chú đã lưu cục bộ.', 'warning');
                return;
            }

            // --- ONLINE: gửi lên server ---
            isSaving = true;
            const mySeq = ++autoSaveInFlightSeq;

            const fd = new FormData();
            fd.append('id',      nid);
            fd.append('title',   tit);
            fd.append('content', cont);
            fd.append('version', String(getNoteContentVersion()));
            appendCsrfToken(fd);

            fetch('api/save_note.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(d => {
                    if (mySeq !== autoSaveInFlightSeq) return;

                    const statusEl  = document.getElementById('saveStatus');
                    const contentEl = document.getElementById('noteContent');

                    if (!d.success && d.conflict) {
                        contentEl.dataset.version = d.version;

                        window.__remoteUpdating = true;
                        try {
                            if (document.activeElement !== contentEl && d.latest_content !== undefined) {
                                contentEl.value = d.latest_content;
                            }
                            if (document.activeElement !== document.getElementById('noteTitle') && d.latest_title !== undefined) {
                                document.getElementById('noteTitle').value = d.latest_title;
                            }
                        } finally {
                            window.__remoteUpdating = false;
                        }

                        statusEl.innerHTML = '<i class="bi bi-arrow-clockwise text-warning"></i> Đồng bộ xong';
                        showToast('Đã cập nhật phiên bản mới nhất, đang lưu lại...', 'warning');

                        clearTimeout(autoSaveRetryTimer);
                        autoSaveRetryTimer = setTimeout(() => {
                            autoSaveRetryTimer = null;
                            autoSave();
                        }, 1000);
                        return;
                    }

                    if (d.success) {
                        statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu';

                        if (!nid && d.note_id) {
                            document.getElementById('noteId').value = d.note_id;
                            currentNoteId = d.note_id;
                            setNoteOwnerToolbarVisible(true);
                        }

                        if (d.version) {
                            contentEl.dataset.version = d.version;
                        }

                        lastAutoSavePersistSig = _autoSavePayloadSignature();
                        liveSearch();
                    } else {
                        statusEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> Lỗi lưu';
                        if (d.message) showToast(d.message, 'danger');
                    }
                })
                .catch(() => {
                    if (mySeq !== autoSaveInFlightSeq) return;

                    document.getElementById('saveStatus').innerHTML =
                        '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Lưu offline</span>';
                    const noteData = {
                        id:         nid || 'temp_' + Date.now(),
                        title:      tit,
                        content:    cont,
                        version:    getNoteContentVersion(),
                        updated_at: new Date().toISOString()
                    };
                    saveNoteOffline(noteData);
                    showToast('Không kết nối mạng. Ghi chú đã được lưu cục bộ.', 'warning');
                })
                .finally(() => {
                    if (mySeq === autoSaveInFlightSeq) {
                        isSaving = false;
                    }
                });
        };

        runCommitted();
    }, 800);
}

// ====================== CHIA SẺ ======================
function shareNote() {
    const noteId = document.getElementById('noteId').value;
    const input  = document.getElementById('share_input').value.trim();
    const perm   = document.getElementById('sharePermission').value;

    if (!noteId) return showAlert('Vui lòng lưu ghi chú trước khi chia sẻ!', 'warning');
    if (!input)  return showAlert('Vui lòng nhập email!', 'warning');

    const fd = new FormData();
    fd.append('note_id',    noteId);
    fd.append('permission', perm);
    fd.append('share_with', input);
    appendCsrfToken(fd);

    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showAlert(d.message || 'Đã thực hiện chia sẻ', d.success ? 'success' : 'danger');
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
                liveSearch();
            }
        })
        .catch(() => showAlert('Lỗi khi chia sẻ!', 'danger'));
}

function loadSharedUsers(noteId) {
    fetch(`api/get_shares.php?note_id=${noteId}`)
        .then(r => r.json())
        .then(users => {
            const list = document.getElementById('sharedUsersList');
            list.innerHTML = '';
            users.forEach(u => {
                list.innerHTML += `
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                        <span><i class="bi bi-person-check text-success"></i> ${escapeHtml(u.display_name)}
                            <small>(${u.permission})</small></span>
                        <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
                    </li>`;
            });
        });
}

function revokeShare(shareId) {
    showConfirm('Thu hồi quyền chia sẻ này?', () => {
        const fd = new FormData();
        fd.append('share_id', shareId);
        fetch('api/revoke_share.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                loadSharedUsers(document.getElementById('noteId').value);
                showAlert(data.message || 'Đã thu hồi quyền chia sẻ', data.success ? 'success' : 'danger');
            })
            .catch(() => showAlert('Lỗi khi thu hồi quyền!', 'danger'));
    });
}

// ====================== KHÓA GHI CHÚ ======================
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    if (isLockedState) {
        _showLockActionPicker(id);
    } else {
        _showLockSetModal(id);
    }
}

function _showLockSetModal(id) {
    _openPasswordModal({
        title: '🔒 Đặt mật khẩu cho ghi chú',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');

            const fd = new FormData();
            fd.append('note_id',          id);
            fd.append('action',           'lock');
            fd.append('password',         pw);
            fd.append('confirm_password', pw2);
            appendCsrfToken(fd);

            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        isLockedState = true;
                        document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
                        liveSearch();
                        return true;
                    }
                    showError(d.message || 'Không thể đặt khóa!');
                    return false;
                });
        }
    });
}

function _showLockActionPicker(id) {
    _openPasswordModal({
        title: '🔒 Ghi chú đang được khóa',
        fields: [
            { id: 'pm_old_pw', placeholder: 'Nhập mật khẩu hiện tại', type: 'password' }
        ],
        actions: [
            { label: 'Gỡ khóa',      style: 'btn-warning', value: 'unlock' },
            { label: 'Đổi mật khẩu', style: 'btn-primary', value: 'change' }
        ],
        onConfirm(vals, showError, actionValue) {
            const [oldPw] = vals;
            if (!oldPw) return showError('Vui lòng nhập mật khẩu hiện tại!');

            const fd = new FormData();
            fd.append('note_id',      id);
            fd.append('old_password', oldPw);
            appendCsrfToken(fd);

            if (actionValue === 'unlock') {
                fd.append('action', 'unlock');
                return fetch('api/lock_note.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            isLockedState = false;
                            document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
                            liveSearch();
                            return true;
                        }
                        showError(d.message || 'Mật khẩu không đúng!');
                        return false;
                    });
            } else {
                fd.append('action', 'verify');
                return fetch('api/lock_note.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(d => {
                        if (!d.success) { showError(d.message || 'Mật khẩu không đúng!'); return false; }
                        passwordModalInstance.hide();
                        setTimeout(() => _showChangePasswordModal(id, oldPw), 300);
                        return false;
                    });
            }
        }
    });
}

function _showChangePasswordModal(id, oldPw) {
    _openPasswordModal({
        title: '🔑 Đặt mật khẩu mới',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');

            const fd = new FormData();
            fd.append('note_id',          id);
            fd.append('action',           'change');
            fd.append('old_password',     oldPw);
            fd.append('password',         pw);
            fd.append('confirm_password', pw2);
            appendCsrfToken(fd);

            return fetch('api/lock_note.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) { liveSearch(); return true; }
                    showError(d.message || 'Không thể đổi mật khẩu!');
                    return false;
                });
        }
    });
}

function _openPasswordModal(config) {
    const titleEl  = document.getElementById('passwordModalTitle');
    const bodyEl   = document.getElementById('passwordModal').querySelector('.modal-body');
    const footerEl = document.getElementById('passwordModal').querySelector('.modal-footer');

    titleEl.textContent = config.title;
    bodyEl.innerHTML = config.fields.map(f =>
        `<input id="${f.id}" type="${f.type}" class="form-control mb-2" placeholder="${f.placeholder}" autocomplete="off">`
    ).join('') + `<div id="pm_error" class="text-danger small mt-1" style="display:none;"></div>`;

    const actions = config.actions || [{ label: 'Xác nhận', style: 'btn-primary', value: 'confirm' }];
    footerEl.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>` +
        actions.map(a => `<button type="button" class="btn ${a.style} pm-action-btn" data-action="${a.value}">${a.label}</button>`).join('');

    const errorEl = document.getElementById('pm_error');

    function showError(msg) {
        errorEl.textContent   = msg;
        errorEl.style.display = 'block';
        footerEl.querySelectorAll('.pm-action-btn').forEach(b => {
            b.disabled    = false;
            b.textContent = b.dataset.origLabel;
        });
    }

    footerEl.querySelectorAll('.pm-action-btn').forEach(btn => {
        btn.dataset.origLabel = btn.textContent;
        btn.addEventListener('click', function () {
            errorEl.style.display = 'none';
            const vals = config.fields.map(f => document.getElementById(f.id).value.trim());
            footerEl.querySelectorAll('.pm-action-btn').forEach(b => { b.disabled = true; });
            btn.textContent = 'Đang xử lý...';

            Promise.resolve(config.onConfirm(vals, showError, btn.dataset.action))
                .then(shouldClose => { if (shouldClose === true) passwordModalInstance.hide(); })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    document.getElementById('passwordModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById(config.fields[0].id)?.focus();
    }, { once: true });

    passwordModalInstance.show();
}

// ====================== XÓA GHI CHÚ ======================
function deleteNote(action) {
    const id = document.getElementById('noteId').value;
    if (!id) return;

    const isPermanent = action === 'permanent';
    const title       = isPermanent ? 'Xóa vĩnh viễn ghi chú?' : 'Chuyển vào thùng rác?';
    const bodyText    = isPermanent
        ? 'Hành động này <strong>không thể hoàn tác</strong>.'
        : 'Ghi chú sẽ được chuyển vào thùng rác.';

    document.getElementById('deleteModalTitle').innerHTML = `<i class="bi bi-trash3"></i> ${title}`;
    document.getElementById('deleteModalBody').innerHTML  = `<p>${bodyText}</p>`;

    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModal.show();

    document.getElementById('confirmDeleteBtn').onclick = function () {
        deleteModal.hide();
        if (isLockedState) {
            _deleteNoteWithPassword(id, action);
        } else {
            _doDeleteNote(id, action);
        }
    };
}

function _doDeleteNote(id, action) {
    const fd = new FormData();
    fd.append('id',         id);
    fd.append('action',     action);
    appendCsrfToken(fd);

    fetch('api/delete_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                showToast(d.message || 'Đã thực hiện thành công', 'success');
                closeAndReload();
            } else if (d.require_password) {
                _deleteNoteWithPassword(id, action);
            } else {
                showToast(d.message || 'Không thể xóa ghi chú!', 'danger');
            }
        })
        .catch(() => showToast('Lỗi kết nối khi xóa ghi chú!', 'danger'));
}

function _deleteNoteWithPassword(id, action) {
    const titleEl    = document.getElementById('passwordModalTitle');
    const inputEl    = document.getElementById('notePasswordInput');
    const errorEl    = document.getElementById('passwordError');
    const confirmBtn = document.getElementById('passwordModalConfirmBtn');

    titleEl.textContent   = '🔒 Nhập mật khẩu để xác nhận xóa';
    inputEl.value         = '';
    errorEl.style.display = 'none';

    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.onclick = function () {
        const pw = inputEl.value.trim();
        if (!pw) {
            errorEl.textContent   = 'Vui lòng nhập mật khẩu!';
            errorEl.style.display = 'block';
            inputEl.focus();
            return;
        }
        newBtn.disabled    = true;
        newBtn.textContent = 'Đang xác nhận...';
        errorEl.style.display = 'none';

        const fd = new FormData();
        fd.append('id',              id);
        fd.append('action',          action);
        fd.append('delete_password', pw);
        appendCsrfToken(fd);

        fetch('api/delete_note.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(d => {
                if (d.success) {
                    showToast(d.message || 'Đã xóa thành công', 'success');
                    passwordModalInstance.hide();
                    closeAndReload();
                } else {
                    errorEl.textContent   = d.message || 'Mật khẩu không đúng!';
                    errorEl.style.display = 'block';
                    inputEl.value         = '';
                    inputEl.focus();
                    newBtn.disabled    = false;
                    newBtn.textContent = 'Xác nhận';
                }
            })
            .catch(() => {
                errorEl.textContent   = 'Lỗi kết nối!';
                errorEl.style.display = 'block';
                newBtn.disabled    = false;
                newBtn.textContent = 'Xác nhận';
            });
    };

    passwordModalInstance.show();
    setTimeout(() => inputEl.focus(), 300);
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    const fd = new FormData();
    fd.append('id', id);
    fetch('api/restore_note.php', { method: 'POST', body: fd })
        .then(() => { showAlert('Khôi phục thành công!', 'success'); closeAndReload(); });
}

// ====================== WEBSOCKET REALTIME ======================
let ws                    = null;
let wsReconnectTimer      = null;
let wsAwaitCloseReconnect = false;
let wsReady               = false;
let currentNoteIdForWS    = null;
let _pollInterval         = null;
let _lastWsOutboundSig    = '';
let _lastWsInboundKey     = '';

const WS_HOST = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':8080';

function connectWebSocket() {
    if (ws) {
        if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
            return;
        }
        if (ws.readyState === WebSocket.CLOSING) {
            if (!wsAwaitCloseReconnect) {
                wsAwaitCloseReconnect = true;
                ws.onopen    = null;
                ws.onmessage = null;
                ws.onclose   = null;
                ws.onerror   = null;
                ws.addEventListener('close', () => {
                    wsAwaitCloseReconnect = false;
                    ws = null;
                    connectWebSocket();
                }, { once: true });
            }
            return;
        }
        ws = null;
    }

    clearTimeout(wsReconnectTimer);
    wsReconnectTimer = null;

    try {
        ws = new WebSocket(WS_HOST);
    } catch (e) {
        console.warn('WebSocket không khả dụng, dùng fallback polling.');
        _startFallbackPolling();
        return;
    }

    const socket = ws;

    socket.onopen = () => {
        if (ws !== socket) return;
        clearTimeout(wsReconnectTimer);
        wsReconnectTimer = null;
        _stopFallbackPolling();
        socket.send(JSON.stringify({ type: 'auth', user_id: currentUserId, user_name: currentUserName }));
        _setWsStatus('connecting');
    };

    socket.onmessage = (event) => {
        if (ws !== socket) return;
        try {
            const data = JSON.parse(event.data);

            if (data.type === 'auth_success') {
                wsReady = true;
                _setWsStatus('online');
                if (currentNoteIdForWS) _wsSend({ type: 'join_note', note_id: currentNoteIdForWS });
            }

            if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                if (data.user_name === currentUserName) return;

                const c = String(data.content ?? '');
                const inboundKey = [
                    data.note_id,
                    data.user_name,
                    data.timestamp ?? '',
                    data.title ?? '',
                    c.length,
                    c.slice(0, 256)
                ].join('\x1e');
                if (inboundKey === _lastWsInboundKey) return;
                _lastWsInboundKey = inboundKey;

                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');

                const isEditingTitle   = document.activeElement === titleEl;
                const isEditingContent = document.activeElement === contentEl;

                window.__remoteUpdating = true;
                try {
                    // FIX: cập nhật version ngay khi nhận WS update
                    // (cả từ typing broadcast lẫn from_save)
                    if (data.version) {
                        contentEl.dataset.version = data.version;
                    }

                    if (data.title !== undefined && !isEditingTitle) {
                        titleEl.value = data.title;
                    }

                    if (data.content !== undefined) {
                        const currentContent  = contentEl.value    || '';
                        const incomingContent = String(data.content);

                        if (!isEditingContent) {
                            contentEl.value = incomingContent;
                        } else {
                            const isDeleting   = incomingContent.length < currentContent.length;
                            const tooDifferent = Math.abs(incomingContent.length - currentContent.length) > 5;

                            if (isDeleting || tooDifferent) {
                                const cursorPos = contentEl.selectionStart;
                                contentEl.value = incomingContent;
                                try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
                            }
                        }
                    }
                } finally {
                    window.__remoteUpdating = false;
                }

                _showTypingIndicator(data.user_name);
            }

            if (data.type === 'presence' && data.note_id == currentNoteIdForWS) {
                _renderPresence(data.users);
            }
        } catch (e) {
            console.error('WS parse error:', e);
        }
    };

    socket.onclose = () => {
        if (ws !== socket) return;
        wsReady = false;
        _setWsStatus('offline');
        ws = null;
        clearTimeout(wsReconnectTimer);
        wsReconnectTimer = setTimeout(() => {
            wsReconnectTimer = null;
            connectWebSocket();
        }, 3000);
    };

    socket.onerror = () => {
        if (ws !== socket) return;
        _setWsStatus('offline');
    };
}

function _wsSend(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj));
}

function _wsSendEditorStateIfChanged() {
    const payload = buildWsNoteUpdatePayload();
    const sig = `${payload.note_id}\x1e${payload.title}\x1e${payload.content}\x1e${payload.version}`;
    if (sig === _lastWsOutboundSig) return;
    _lastWsOutboundSig = sig;
    _wsSend(payload);
}

function _startFallbackPolling() {
    if (_pollInterval) return;
    _setWsStatus('polling');
    _pollInterval = setInterval(() => {
        if (!currentNoteIdForWS) return;
        fetch(`api/get_notes.php?note_id=${currentNoteIdForWS}`)
            .then(r => r.ok ? r.json() : null)
            .then(note => {
                if (!note) return;
                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');
                window.__remoteUpdating = true;
                try {
                    if (document.activeElement !== titleEl)   titleEl.value   = note.title   ?? '';
                    if (document.activeElement !== contentEl) contentEl.value = note.content ?? '';
                    if (note.version) contentEl.dataset.version = note.version;
                } finally {
                    window.__remoteUpdating = false;
                }
            })
            .catch(() => {});
    }, 4000);
}

function _stopFallbackPolling() { clearInterval(_pollInterval); _pollInterval = null; }

function _setWsStatus(state) {
    const el = document.getElementById('wsStatusBadge');
    if (!el) return;
    const map = {
        online:     { text: '● Trực tuyến',      cls: 'bg-success'   },
        offline:    { text: '● Mất kết nối',      cls: 'bg-danger'    },
        connecting: { text: '● Đang kết nối…',   cls: 'bg-warning'   },
        polling:    { text: '● Chế độ dự phòng', cls: 'bg-secondary' }
    };
    const s        = map[state] || map.offline;
    el.textContent = s.text;
    el.className   = `badge ${s.cls} ms-2 small`;
}

function _renderPresence(users) {
    const el = document.getElementById('wsPresenceBar');
    if (!el) return;
    const others = users.filter(u => u !== currentUserName);
    el.innerHTML = others.length
        ? `<i class="bi bi-people-fill"></i> Đang xem cùng: <strong>${others.map(u => escapeHtml(u)).join(', ')}</strong>`
        : '';
}

let _typingTimer = null;
function _showTypingIndicator(userName) {
    const el = document.getElementById('wsTypingIndicator');
    if (!el || userName === currentUserName) return;
    el.textContent   = `✏️ ${escapeHtml(userName)} đang chỉnh sửa…`;
    el.style.display = 'block';
    clearTimeout(_typingTimer);
    _typingTimer = setTimeout(() => { el.style.display = 'none'; }, 2500);
}

function startRealtimeForNote(noteId, permission) {
    if (permission !== 'edit' && permission !== 'owner') return;
    if (currentNoteIdForWS !== noteId) {
        if (currentNoteIdForWS && wsReady) {
            _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
        }
        _lastWsOutboundSig = '';
        _lastWsInboundKey  = '';
    }
    currentNoteIdForWS = noteId;
    connectWebSocket();
    if (wsReady) _wsSend({ type: 'join_note', note_id: noteId });
}

function stopRealtime() {
    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = null;
    if (currentNoteIdForWS) _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
    currentNoteIdForWS = null;
    _lastWsOutboundSig = '';
    _lastWsInboundKey  = '';
    _stopFallbackPolling();
    const p = document.getElementById('wsPresenceBar');
    const t = document.getElementById('wsTypingIndicator');
    if (p) p.textContent   = '';
    if (t) { t.textContent = ''; t.style.display = 'none'; }
}

// ====================== ẢNH ======================
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('image',   f);
    fd.append('note_id', nid);

    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
            } else {
                showAlert(d.message || 'Không thể upload ảnh', 'danger');
            }
            document.getElementById('imageInput').value = '';
        })
        .catch(() => showAlert('Lỗi kết nối khi upload ảnh!', 'danger'));
}

function renderImage(path, id, perm) {
    const del = perm === 'owner'
        ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle"
             style="width:24px;height:24px;margin:-8px -8px 0 0;"
             onclick="deleteImage(${id},this)"><i class="bi bi-x"></i></button>`
        : '';
    document.getElementById('imagePreviewContainer').innerHTML +=
        `<div class="position-relative shadow-sm rounded">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">${del}
         </div>`;
}

function deleteImage(id, btn) {
    showConfirm('Xóa ảnh này?', () => {
        fetch('api/delete_image.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `id=${id}`
        }).then(r => r.json()).then(d => { if (d.success) btn.parentElement.remove(); });
    });
}

// ====================== LABEL ======================
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list')
        .then(r => r.json())
        .then(ls => {
            const bar = document.getElementById('labelFilterBar');
            bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}"
                onclick="filterLabel(null)">Tất cả</button>`;
            ls.forEach(l => {
                bar.innerHTML += `
                    <div class="btn-group btn-group-sm">
                        <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}"
                            onclick="filterLabel(${l.id})">${escapeHtml(l.name)}</button>
                        <button class="btn btn-outline-secondary"
                            onclick="event.stopPropagation();renameLabel(${l.id},'${escapeHtml(l.name).replace(/'/g, "\\'")}')">
                            <i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger"
                            onclick="event.stopPropagation();deleteLabel(${l.id})">
                            <i class="bi bi-x"></i></button>
                    </div>`;
            });
            if (callback) callback();
        });
}

function filterLabel(id) { currentLabelId = id; loadFilterLabels(() => liveSearch()); }

function addNewLabel() {
    const name = document.getElementById('newLabelName').value.trim();
    if (!name) return;
    fetch('api/manage_labels.php?action=add', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `name=${encodeURIComponent(name)}`
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `id=${id}&name=${encodeURIComponent(newName.trim())}`
    }).then(() => loadFilterLabels(() => liveSearch()));
}

function deleteLabel(id) {
    showConfirm('Xóa nhãn này?', () => {
        fetch('api/manage_labels.php?action=delete', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `id=${id}`
        }).then(() => {
            if (currentLabelId == id) currentLabelId = null;
            loadFilterLabels(() => liveSearch());
        });
    });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list')
        .then(r => r.json())
        .then(ls => {
            const s = document.getElementById('labelSelector');
            s.innerHTML = '<option value="">+ Nhãn</option>';
            ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
        });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`)
        .then(r => r.json())
        .then(ls => {
            const c = document.getElementById('noteLabelsContainer');
            c.innerHTML = '';
            ls.forEach(l => c.innerHTML +=
                `<span class="badge bg-secondary">${escapeHtml(l.name)}
                 <i class="bi bi-x-circle-fill cp" onclick="removeLabel(${nid},${l.id})"></i></span>`);
        });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if (!lid) return;
    fetch('api/set_note_label.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `note_id=${nid}&label_id=${lid}&action=add`
    }).then(() => {
        loadLabelsForNote(nid);
        liveSearch();
        document.getElementById('labelSelector').value = '';
    });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `note_id=${nid}&label_id=${lid}&action=remove`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== PROFILE / SETTINGS ======================
function saveProfile() {
    const fd = new FormData();

    const avatarFile = document.getElementById('inputAvatar').files[0];
    if (avatarFile) fd.append('avatar', avatarFile);

    const fontSize  = document.getElementById('settingFontSize').value;
    const theme     = document.getElementById('settingTheme').value;
    const noteColor = document.getElementById('settingNoteColor').value;

    fd.append('font_size',   fontSize);
    fd.append('theme_color', theme);
    fd.append('note_color',  noteColor);
    appendCsrfToken(fd);

    applyTheme(theme);
    document.documentElement.style.fontSize = fontSize;
    document.body.style.fontSize            = fontSize;
    document.documentElement.style.setProperty('--note-default-color', noteColor);

    fetch('api/update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (avatarFile) {
                    location.reload();
                } else {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                    if (modal) modal.hide();
                    showToast('Đã lưu thay đổi thành công!', 'success');
                }
            } else {
                showAlert(data.message || 'Lỗi cập nhật!', 'danger');
            }
        })
        .catch(() => showAlert('Lỗi kết nối!', 'danger'));
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatar').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('noteapp_theme', theme);
}

// ====================== TIỆN ÍCH ======================
function setView(v) {
    document.getElementById('notesContainer').className =
        v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    `id=${id}&is_pinned=${state}`
    }).then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) { showAlert('Vui lòng lưu ghi chú trước khi đổi màu!', 'warning'); return; }

    const fd = new FormData();
    fd.append('id',         id);
    fd.append('color',      color || '');
    appendCsrfToken(fd);

    fetch('api/change_color.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                const modalWrapper = document.getElementById('modalContentWrapper');
                modalWrapper.style.backgroundColor = color || '';
                modalWrapper.style.setProperty('--note-individual-color', color || '');
                liveSearch();
                showAlert('Đã đổi màu ghi chú thành công', 'success');
            } else {
                showAlert('Không thể đổi màu ghi chú!', 'danger');
            }
        })
        .catch(() => showAlert('Lỗi kết nối!', 'danger'));
}

function formatRelativeTime(datetime) {
    if (!datetime) return '';
    const date    = new Date(datetime);
    const diffMin = Math.floor((new Date() - date) / 60000);
    if (diffMin < 1)    return 'Vừa xong';
    if (diffMin < 60)   return diffMin + ' phút trước';
    if (diffMin < 1440) return Math.floor(diffMin / 60) + ' giờ trước';
    return date.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' });
}

function escapeHtml(s) {
    if (!s) return '';
    return s.toString()
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}

// ====================== TOAST ======================
function showToast(message, type = 'success') {
    const colorMap = { success: 'success', warning: 'warning', danger: 'danger', info: 'info' };
    const iconMap  = { success: '✅', warning: '⚠️', danger: '❌', info: 'ℹ️' };
    const bg       = colorMap[type] || 'secondary';
    const icon     = iconMap[type]  || 'ℹ️';

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${bg} border-0 position-fixed bottom-0 end-0 m-3`;
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${icon} ${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 2500 });
    bsToast.show();
    setTimeout(() => toast.remove(), 2800);
}

// ====================== CUSTOM MODAL ======================
function showAlert(message, type = 'info') {
    const titleEl = document.getElementById('customAlertTitle');
    const bodyEl  = document.getElementById('customAlertBody');
    const btnEl   = document.getElementById('customAlertBtn');

    const map = {
        success: { html: `<i class="bi bi-check-circle-fill text-success"></i> Thành công`, cls: 'btn btn-success px-4' },
        danger:  { html: `<i class="bi bi-x-circle-fill text-danger"></i> Lỗi`,             cls: 'btn btn-danger px-4'  },
        error:   { html: `<i class="bi bi-x-circle-fill text-danger"></i> Lỗi`,             cls: 'btn btn-danger px-4'  },
        warning: { html: `<i class="bi bi-exclamation-triangle-fill text-warning"></i> Cảnh báo`, cls: 'btn btn-warning px-4' },
        info:    { html: `<i class="bi bi-info-circle"></i> Thông báo`,                     cls: 'btn btn-primary px-4' }
    };
    const m = map[type] || map.info;
    titleEl.innerHTML = m.html;
    btnEl.className   = m.cls;
    bodyEl.innerHTML  = `<p class="mb-0">${message}</p>`;
    customAlertModal.show();
}

function showConfirm(message, onConfirm, onCancel = null) {
    document.getElementById('customConfirmBody').innerHTML = `<p>${message}</p>`;

    const okBtn     = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');

    okBtn.replaceWith(okBtn.cloneNode(true));
    cancelBtn.replaceWith(cancelBtn.cloneNode(true));

    document.getElementById('confirmOkBtn').onclick = () => {
        customConfirmModal.hide();
        if (onConfirm) onConfirm();
    };
    document.getElementById('confirmCancelBtn').onclick = () => {
        customConfirmModal.hide();
        if (onCancel) onCancel();
    };

    customConfirmModal.show();
}

// ====================== INDEXEDDB / OFFLINE ======================
let db = null;

function initIndexedDB() {
    const request = indexedDB.open('NoteAppDB', 3);

    request.onupgradeneeded = function (event) {
        db = event.target.result;
        if (!db.objectStoreNames.contains('notes')) {
            const store = db.createObjectStore('notes', { keyPath: 'id' });
            store.createIndex('updated_at', 'updated_at');
            store.createIndex('isTemp',     'isTemp');
        }
    };

    request.onsuccess = function (event) {
        db = event.target.result;
        console.log('[IndexedDB] Initialized v3');
    };

    request.onerror = function (event) {
        console.error('[IndexedDB] Error:', event.target.error);
    };
}

function isNoteTemp(id) {
    return typeof id === 'string' && id.startsWith('temp_');
}

function saveNoteOffline(note) {
    if (!db) return;
    const noteToSave = {
        ...note,
        isTemp:     isNoteTemp(note.id),
        syncStatus: 'pending',
        updated_at: new Date().toISOString()
    };
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(noteToSave);
    console.log(`[Offline] Saved note ${note.id}`);
}

async function getAllOfflineNotes() {
    if (!db) return [];
    return new Promise((resolve) => {
        const tx    = db.transaction('notes', 'readonly');
        const store = tx.objectStore('notes');
        const req   = store.getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => resolve([]);
    });
}

async function syncOfflineNotes() {
    if (!navigator.onLine || !db) return;

    const offlineNotes = await getAllOfflineNotes();
    if (offlineNotes.length === 0) return;
    console.log(`[Offline Sync] Found ${offlineNotes.length} notes`);

    let syncedCount = 0;
    for (const note of offlineNotes) {
        try {
            const fd = new FormData();
            fd.append('id',         isNoteTemp(note.id) ? 0 : note.id);
            fd.append('title',      note.title   || '');
            fd.append('content',    note.content || '');
            fd.append('version',    note.version || 1);
            appendCsrfToken(fd);

            const res  = await fetch('api/save_note.php', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                syncedCount++;
                const delTx = db.transaction(['notes'], 'readwrite');
                delTx.objectStore('notes').delete(note.id);
                console.log(`[Offline] Synced ${note.id} → ${data.note_id}`);
            }
        } catch (err) {
            console.error('Sync failed for note', note.id, err);
        }
    }

    if (syncedCount > 0) {
        showToast(`Đã đồng bộ ${syncedCount} ghi chú từ chế độ offline`, 'success');
        setTimeout(liveSearch, 800);
    }
}

async function loadNotesOfflineFallback() {
    const offlineNotes = await getAllOfflineNotes();
    if (offlineNotes.length > 0) {
        offlineNotes.forEach(n => {
            if (isNoteTemp(n.id)) n.title = '📴 ' + (n.title || 'Ghi chú tạm');
        });
        renderNotes(offlineNotes);
        showToast('Đang hiển thị ghi chú offline', 'warning');
        return true;
    }
    return false;
}
//----//

database.sql
//code//
-- ============================================================
-- NoteApp Pro - Database Schema (ĐÃ SỬA LỖI)
-- Sửa: thêm các cột bị thiếu trong notes, chuẩn hóa tên cột users
-- ============================================================

CREATE DATABASE IF NOT EXISTS note_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_management;

-- ============================================================
-- Bảng Users
-- SỬA: đổi 'theme' -> 'theme_color', 'font_size' default -> '16px'
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activation_token VARCHAR(100),
    is_activated TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    font_size VARCHAR(10) DEFAULT '16px',       -- SỬA: đổi 'medium' -> '16px'
    theme_color VARCHAR(10) DEFAULT 'light',     -- SỬA: đổi tên cột 'theme' -> 'theme_color'
    note_color VARCHAR(20) DEFAULT '#ffffff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Bảng Notes
-- SỬA: thêm is_trashed, color, password_hash, pinned_at
-- ============================================================
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT,
    is_pinned TINYINT(1) DEFAULT 0,
    pinned_at DATETIME DEFAULT NULL,            -- THÊM MỚI
    is_trashed TINYINT(1) DEFAULT 0,            -- THÊM MỚI: soft-delete
    color VARCHAR(20) DEFAULT NULL,             -- THÊM MỚI: màu nền ghi chú
    password_hash VARCHAR(255) DEFAULT NULL,    -- THÊM MỚI: thay note_password
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note Images
-- ============================================================
CREATE TABLE note_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Labels
-- ============================================================
CREATE TABLE labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note_Labels (N-N)
-- ============================================================
CREATE TABLE note_labels (
    note_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (note_id, label_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Shared Notes
-- ============================================================
CREATE TABLE shared_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    owner_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    permission ENUM('read', 'edit') DEFAULT 'read',
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Script ALTER TABLE nếu database đã tồn tại (chạy thay vì tạo mới)
-- ============================================================
-- ALTER TABLE users
--   CHANGE COLUMN theme theme_color VARCHAR(10) DEFAULT 'light',
--   MODIFY COLUMN font_size VARCHAR(10) DEFAULT '16px';

-- ALTER TABLE notes
--   ADD COLUMN IF NOT EXISTS is_trashed TINYINT(1) DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS pinned_at DATETIME DEFAULT NULL;

-- UPDATE notes SET is_trashed = 0 WHERE is_trashed IS NULL;
//----//

index.php
//code//
<?php
require_once 'api/auth_helper.php';
check_login();

if (!isset($_SESSION['last_regen']) || time() - $_SESSION['last_regen'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}
// Avatar mặc định
$default_avatar = 'uploads/avatars/default-avatar.png';

$user_font_size  = $_SESSION['font_size']   ?? '16px';
$user_theme      = $_SESSION['theme_color'] ?? 'light';
$user_note_color = $_SESSION['note_color']  ?? '#ffffff';

$user_avatar = !empty($_SESSION['avatar'])
    ? $_SESSION['avatar']
    : $default_avatar;
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= htmlspecialchars($user_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteApp Pro</title>

    <script>
        (function() {
            const saved = localStorage.getItem('noteapp_theme');
            const phpTheme = "<?= htmlspecialchars($user_theme) ?>";
            document.documentElement.setAttribute('data-bs-theme', saved || phpTheme);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body { 
            font-size: <?= htmlspecialchars($user_font_size) ?>; 
        }
        
        :root {
            --note-default-color: <?= htmlspecialchars($user_note_color) ?>;
        }
        
        .note-card {
            background-color: var(--note-default-color) !important;
        }
    </style>
</head>
<body class="bg-body text-body">

<nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">📝 NoteApp</a>
        <form class="d-flex mx-auto w-50" onsubmit="return false;">
            <input class="form-control me-2" type="search" id="searchInput" placeholder="Tìm kiếm ghi chú..." oninput="liveSearch()">
        </form>
        <div class="d-flex align-items-center gap-3">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
            <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" 
                 class="nav-avatar rounded-circle" 
                 onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()" 
                 title="Cài đặt tài khoản"
                 style="width:32px;height:32px;object-fit:cover;cursor:pointer;">
            <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['is_activated']) && $_SESSION['is_activated'] == 0): ?>
<div class="alert alert-warning alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Tài khoản chưa được xác minh!</strong>
    Vui lòng kiểm tra email và click vào link kích hoạt.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="container mt-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between bg-body-tertiary p-3 rounded mb-4 shadow-sm border">
        <div id="labelFilterBar" class="d-flex flex-wrap gap-2 align-items-center"></div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <div class="input-group input-group-sm w-auto" id="addLabelGroup">
                <input type="text" id="newLabelName" class="form-control" placeholder="Tên nhãn mới...">
                <button class="btn btn-primary" onclick="addNewLabel()">Tạo</button>
            </div>
            <button id="btnViewShared" class="btn btn-sm btn-outline-info" onclick="setViewMode('shared')">
                <i class="bi bi-people"></i> Được chia sẻ
            </button>
            <button id="btnViewTrash" class="btn btn-sm btn-outline-danger" onclick="setViewMode('trash')">
                <i class="bi bi-trash3"></i> Thùng rác
            </button>
            <button id="btnViewMyNotes" class="btn btn-sm btn-primary" onclick="setViewMode('my_notes')" style="display:none;">
                <i class="bi bi-house"></i> Ghi chú của tôi
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button id="btnCreateNote" class="btn btn-primary shadow-sm px-4 fw-bold" onclick="openNoteModal()">
            <i class="bi bi-plus-lg"></i> Tạo ghi chú mới
        </button>
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;"></h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
</div>

<!-- ==================== MODALS ==================== -->
<?php include 'modals.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- APP CONFIG -->
<script>
    window.APP_CONFIG = {
        userId:      <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        userName:    "<?= addslashes($_SESSION['display_name'] ?? 'User') ?>",
        csrf_token:  "<?= $_SESSION['csrf_token'] ?? '' ?>"
    };
</script>

<!-- Main JS -->
<script src="assets/js/app.js"></script>

<!-- Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(() => console.log('SW registered'))
            .catch(err => console.log('SW failed:', err));
    });
}
</script>

<!-- Floating Button -->
<button class="floating-create btn btn-primary btn-lg rounded-circle shadow" 
        onclick="openNoteModal()" 
        style="position:fixed; bottom:25px; right:25px; width:65px; height:65px; z-index:1050;">
    <i class="bi bi-plus-lg fs-3"></i>
</button>

</body>
</html>
//----//

login.php
//code//
<?php
session_start();
require_once 'database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Yêu cầu không hợp lệ!";
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Vui lòng nhập đầy đủ thông tin!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = "Email hoặc mật khẩu không chính xác!";
                } else {
                    // Auto login dù chưa activate
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['display_name'] = $user['display_name'];
                    $_SESSION['avatar']       = $user['avatar'] ?? 'uploads/avatars/default-avatar.png';
                    $_SESSION['font_size']    = $user['font_size'] ?? '16px';
                    $_SESSION['theme_color']  = $user['theme_color'] ?? 'light';
                    $_SESSION['note_color']   = $user['note_color'] ?? '#ffffff';
                    $_SESSION['is_activated'] = (int)$user['is_activated'];

                    session_regenerate_id(true);

                    header("Location: index.php");
                    exit;
                }
            } catch (PDOException $e) {
                $error = "Lỗi hệ thống. Vui lòng thử lại sau!";
                error_log("Login Error: " . $e->getMessage());
            }
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Note App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7f6; height:100vh; display:flex; align-items:center; }
        .login-card { border:none; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card login-card p-4">
                <h2 class="text-center mb-4 fw-bold text-primary">Đăng Nhập</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['activated'])): ?>
                    <div class="alert alert-success">Kích hoạt tài khoản thành công!</div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                </form>

                <div class="mt-4 text-center">
                    <a href="reset_password.php" class="text-decoration-none small">Quên mật khẩu?</a>
                    <hr>
                    <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
//----//

logout.php
//code//
<?php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>
//----//

mail_config.php
//code//
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendActivationEmail($toEmail, $displayName, $token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $activationLink = "http://localhost/activate.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Kích hoạt tài khoản Note App';

        $mail->Body = "
            <div style='font-family:Arial;line-height:1.6; max-width:600px;margin:auto;border:1px solid #eee;padding:20px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Cảm ơn bạn đã đăng ký Note App.</p>
                <p>Click nút bên dưới để kích hoạt tài khoản:</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='{$activationLink}' style='display:inline-block;padding:12px 30px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;'>Kích hoạt ngay</a>
                </div>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Activation Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi OTP khôi phục mật khẩu
 */
function sendResetOTPEmail($toEmail, $displayName, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP khôi phục mật khẩu';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:8px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Mã OTP của bạn là:</p>
                <h1 style='text-align:center;color:#0d6efd;letter-spacing:8px;'>{$otp}</h1>
                <p>Mã này có hiệu lực trong 15 phút.</p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi Link Reset Password
 */
function sendResetLinkEmail($toEmail, $displayName, $reset_token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $resetLink = "http://localhost/reset_password.php?token=" . $reset_token;

        $mail->isHTML(true);
        $mail->Subject = 'Đặt lại mật khẩu - Note App';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản Note App.</p>
                <p>Vui lòng click vào nút bên dưới để đặt lại mật khẩu:</p>
                
                <div style='text-align:center; margin:35px 0;'>
                    <a href='{$resetLink}' 
                       style='display:inline-block; padding:14px 32px; background:#0d6efd; color:white; 
                              text-decoration:none; border-radius:6px; font-weight:bold; font-size:16px;'>
                        ĐẶT LẠI MẬT KHẨU
                    </a>
                </div>
                
                <p style='color:#666; font-size:14px;'>
                    Link này có hiệu lực trong <strong>15 phút</strong>.<br>
                    Nếu bạn không yêu cầu, vui lòng bỏ qua email này.
                </p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset Link Email Error: " . $e->getMessage());
        return false;
    }
}
/**
 * Gửi thông báo mật khẩu đã thay đổi
 */
function sendPasswordChangedNotification($toEmail, $displayName)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Thông báo: Mật khẩu tài khoản Note App đã được thay đổi';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:20px; border:1px solid #ddd; border-radius:8px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p>Mật khẩu tài khoản Note App của bạn vừa được thay đổi thành công.</p>
                <p>Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ ngay với chúng tôi.</p>
                <p>Trân trọng,<br>Đội ngũ Note App</p>
            </div>
        ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Password Changed Notification Error: " . $e->getMessage());
    }
}
/**
 * Gửi email thông báo khi note được chia sẻ (Better Approach)
 */
function sendShareNotification($toEmail, $displayName, $sharerName, $noteTitle)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Bạn được chia sẻ một ghi chú';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p><strong>{$sharerName}</strong> đã chia sẻ một ghi chú với bạn:</p>
                <p style='font-size:18px; font-weight:600; color:#333;'>📝 {$noteTitle}</p>
                <p>Bạn có thể xem ngay trong phần <strong>Được chia sẻ</strong>.</p>
                <p style='color:#666;'>Trân trọng,<br>Đội ngũ Note App</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Share Notification Error: " . $e->getMessage());
        return false;
    }
}
//----//

manifest.json
//code//
{
    "name": "NoteApp Pro",
    "short_name": "NoteApp",
    "description": "Ứng dụng ghi chú thông minh",
    "start_url": "/index.php",
    "display": "standalone",
    "background_color": "#f8fafc",
    "theme_color": "#6ea8ff",
    "orientation": "portrait-primary",
    "icons": [
        {
            "src": "/uploads/avatars/default-avatar.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/uploads/avatars/default-avatar.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}
//----//

modals.php
//code//
<!-- Modal ghi chú chính -->
<div class="modal fade" id="noteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg" id="modalContentWrapper">
            <div class="modal-header border-0 pb-0">
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent" placeholder="Tiêu đề..." oninput="autoSave()">
                <span id="wsStatusBadge" class="badge bg-secondary ms-2 small" style="display:none;"></span>
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <div id="wsPresenceBar" class="alert alert-success py-1 small mb-2" style="min-height:0;"></div>
                <div id="wsTypingIndicator" class="text-muted small fst-italic mb-2" style="display:none;"></div>

                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10" placeholder="Bạn đang nghĩ gì?..." oninput="autoSave()"></textarea>
                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mb-3"></div>

                <!-- Color -->
                <div id="colorSection" class="mb-3" style="display:none;">
                    <span class="small text-muted me-2"><i class="bi bi-palette"></i> Màu:</span>
                    <span class="color-btn" style="background:#ffffff" onclick="changeColor('')"></span>
                    <span class="color-btn" style="background:#f28b82" onclick="changeColor('#f28b82')"></span>
                    <span class="color-btn" style="background:#fbbc04" onclick="changeColor('#fbbc04')"></span>
                    <span class="color-btn" style="background:#fff475" onclick="changeColor('#fff475')"></span>
                    <span class="color-btn" style="background:#ccff90" onclick="changeColor('#ccff90')"></span>
                    <span class="color-btn" style="background:#a7ffeb" onclick="changeColor('#a7ffeb')"></span>
                    <span class="color-btn" style="background:#cbf0f8" onclick="changeColor('#cbf0f8')"></span>
                    <span class="color-btn" style="background:#d7aefb" onclick="changeColor('#d7aefb')"></span>
                </div>

                <!-- Share -->
                <div class="p-3 bg-body-secondary rounded border mb-3" id="shareManagerSection" style="display:none;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus"></i> Chia sẻ ghi chú này</h6>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="share_input" class="form-control" placeholder="Nhập email (cách nhau bởi dấu phẩy)...">
                        <select id="sharePermission" class="form-select" style="max-width: 140px;">
                            <option value="read">Chỉ xem</option>
                            <option value="edit">Cho phép sửa</option>
                        </select>
                        <button class="btn btn-success" onclick="shareNote()">Chia sẻ</button>
                    </div>
                    <small class="text-muted">Ví dụ: user1@gmail.com, user2@gmail.com</small>
                    <ul id="sharedUsersList" class="list-group list-group-flush small mt-3"></ul>
                </div>

                <!-- Tools -->
                <div class="p-3 bg-body-tertiary rounded border" id="toolsSection" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-image"></i> Ảnh
                                <input type="file" id="imageInput" hidden accept="image/*" onchange="uploadImage()">
                            </label>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-warning btn-sm w-100" id="btnLock" onclick="toggleLock()">
                                <i class="bi bi-lock"></i> Khóa
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select id="labelSelector" class="form-select form-select-sm" onchange="addLabelToNote()">
                                <option value="">+ Nhãn</option>
                            </select>
                        </div>
                    </div>
                    <div id="noteLabelsContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>
            </div>

            <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-danger btn-sm" id="btnTrashNote" onclick="deleteNote('trash')" style="display:none;">
                        <i class="bi bi-trash"></i> Xóa (Vào thùng rác)
                    </button>
                    <button class="btn btn-success btn-sm" id="btnRestoreNote" onclick="restoreNote()" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                    </button>
                    <button class="btn btn-danger btn-sm ms-2" id="btnDeletePermanent" onclick="deleteNote('permanent')" style="display:none;">
                        <i class="bi bi-x-octagon"></i> Xóa vĩnh viễn
                    </button>
                </div>
                <span id="saveStatus" class="text-muted small fst-italic"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Profile -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>" class="rounded-circle mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                <div class="mb-4">
                    <label class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-camera"></i> Đổi ảnh đại diện
                        <input type="file" id="inputAvatar" hidden accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <hr>
                <div class="row text-start g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kích thước chữ</label>
                        <select id="settingFontSize" class="form-select">
                            <option value="14px">Nhỏ</option>
                            <option value="16px" selected>Vừa</option>
                            <option value="18px">Lớn</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Giao diện</label>
                        <select id="settingTheme" class="form-select">
                            <option value="light">Sáng</option>
                            <option value="dark">Tối</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Màu ghi chú</label>
                        <input type="color" id="settingNoteColor" class="form-control form-control-color w-100" value="<?= htmlspecialchars($user_note_color) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success px-4" onclick="saveProfile()">
                    <i class="bi bi-check2"></i> Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nhập Mật Khẩu -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle">🔒 Nhập mật khẩu ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="password" id="notePasswordInput" class="form-control" placeholder="Nhập mật khẩu..." autocomplete="current-password">
                <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" id="passwordModalConfirmBtn" class="btn btn-primary" onclick="submitNotePassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Xác nhận Xóa -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title text-danger" id="deleteModalTitle">
                    <i class="bi bi-trash3"></i> Xác nhận xóa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteModalBody">
                <!-- Nội dung sẽ được thay đổi bằng JS -->
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Xác nhận</button>
            </div>
        </div>
    </div>
</div>
<!-- ==================== CUSTOM ALERT / CONFIRM MODAL ==================== -->
<div class="modal fade" id="customAlertModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="customAlertTitle">Thông báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4" id="customAlertBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-primary px-4" id="customAlertBtn" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="customConfirmModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title text-warning" id="customConfirmTitle">Xác nhận</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customConfirmBody"></div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" id="confirmCancelBtn">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirmOkBtn">Xác nhận</button>
            </div>
        </div>
    </div>
</div>


//----//

register.php
//code//
<?php
require_once 'database.php';
require_once 'mail_config.php';

session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Yêu cầu không hợp lệ!";
    } else {
        $email         = trim($_POST['email'] ?? '');
        $display_name  = trim($_POST['display_name'] ?? '');
        $password      = $_POST['password'] ?? '';
        $confirm_pass  = $_POST['confirm_password'] ?? '';

        if (empty($email) || empty($display_name) || empty($password)) {
            $error = "Vui lòng nhập đầy đủ thông tin!";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email không hợp lệ!";
        } elseif (strlen($display_name) < 3 || strlen($display_name) > 50) {
            $error = "Tên hiển thị phải từ 3-50 ký tự!";
        } elseif (strlen($password) < 6) {
            $error = "Mật khẩu phải có ít nhất 6 ký tự!";
        } elseif ($password !== $confirm_pass) {
            $error = "Mật khẩu xác nhận không khớp!";
        } else {
            try {
                // Kiểm tra email tồn tại
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "Email này đã được sử dụng!";
                } else {
                    $activation_token = bin2hex(random_bytes(32));
                    $activation_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $pdo->prepare("
                        INSERT INTO users 
                        (email, display_name, password_hash, activation_token, activation_expiry, is_activated)
                        VALUES (?, ?, ?, ?, ?, 0)
                    ");

                    $stmt->execute([$email, $display_name, $hashed_password, $activation_token, $activation_expiry]);

                    $user_id = $pdo->lastInsertId();

                    // Auto login
                    $_SESSION['user_id']      = $user_id;
                    $_SESSION['display_name'] = $display_name;
                    $_SESSION['avatar']       = 'uploads/avatars/default-avatar.png';
                    $_SESSION['font_size']    = '16px';
                    $_SESSION['theme_color']  = 'light';
                    $_SESSION['note_color']   = '#ffffff';
                    $_SESSION['is_activated'] = 0;

                    session_regenerate_id(true);

                    // Gửi mail
                    $mailSent = sendActivationEmail($email, $display_name, $activation_token);

                    if ($mailSent) {
                        header("Location: index.php?registered=1");
                        exit;
                    } else {
                        $error = "Đăng ký thành công nhưng không gửi được email kích hoạt. Vui lòng liên hệ admin.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Lỗi hệ thống. Vui lòng thử lại sau!";
                error_log("Register Error: " . $e->getMessage());
            }
        }
    }
}

// Tạo CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Note App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">Đăng ký tài khoản</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Tên hiển thị</label>
                            <input type="text" name="display_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label>Xác nhận mật khẩu</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Đăng ký ngay</button>
                    </form>
                    <div class="mt-3 text-center">
                        Đã có tài khoản? <a href="login.php">Đăng nhập</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
//----//

reset_password.php
//code//
<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once 'database.php';
require_once 'mail_config.php';

$step = 1;
$message = '';
$token_verified = false;

// Xử lý token từ link email
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE reset_token = ? 
          AND reset_token_expiry > NOW() 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $step = 2;
        $token_verified = true;
    } else {
        $message = "<div class='alert alert-danger'>Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn!</div>";
    }
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'] ?? '') {
        $message = "<div class='alert alert-danger'>Yêu cầu không hợp lệ (CSRF)!</div>";
    } else {
        $action = $_POST['action'] ?? '';

        // === GỬI OTP HOẶC LINK RESET ===
        if ($action === 'send_reset') {
            $email = trim($_POST['email'] ?? '');
            $type  = $_POST['type'] ?? 'otp'; // otp hoặc link

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "<div class='alert alert-danger'>Email không hợp lệ!</div>";
            } else {
                $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $reset_token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                    // Lưu token
                    $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
                        ->execute([$reset_token, $expiry, $user['id']]);

                    if ($type === 'link') {
                        $sent = sendResetLinkEmail($email, $user['display_name'], $reset_token);
                        $msg = "Link đặt lại mật khẩu đã được gửi đến email của bạn!";
                    } else {
                        $otp = rand(100000, 999999);
                        $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?")
                            ->execute([$otp, $user['id']]);

                        $sent = sendResetOTPEmail($email, $user['display_name'], $otp);
                        $msg = "Mã OTP đã được gửi đến email của bạn!";
                    }

                    if ($sent) {
                        $_SESSION['reset_user_id'] = $user['id'];
                        $step = 2;
                        $message = "<div class='alert alert-success'>$msg</div>";
                    } else {
                        $message = "<div class='alert alert-danger'>Không thể gửi email. Vui lòng thử lại sau.</div>";
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Email không tồn tại trong hệ thống!</div>";
                }
            }
        }

        // === ĐỔI MẬT KHẨU ===
        elseif ($action === 'reset_password') {
            $input       = trim($_POST['otp'] ?? '');           // OTP hoặc "verified_via_link"
            $new_pass    = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';
            $user_id     = $_SESSION['reset_user_id'] ?? 0;

            if (strlen($new_pass) < 6) {
                $message = "<div class='alert alert-danger'>Mật khẩu phải có ít nhất 6 ký tự!</div>";
                $step = 2;
            } elseif ($new_pass !== $confirm_pass) {
                $message = "<div class='alert alert-danger'>Mật khẩu xác nhận không khớp!</div>";
                $step = 2;
            } elseif ($user_id == 0) {
                $message = "<div class='alert alert-danger'>Phiên đặt lại mật khẩu đã hết hạn!</div>";
                $step = 1;
            } else {
                $stmt = $pdo->prepare("
                    SELECT reset_token, reset_token_expiry 
                    FROM users WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $is_valid = $token_verified || 
                    (
                        $user && 
                        $user['reset_token'] == $input && 
                        $user['reset_token_expiry'] > date('Y-m-d H:i:s')
                    );

                if ($is_valid) {
                    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

                    $pdo->prepare("
                        UPDATE users 
                        SET password_hash = ?, 
                            reset_token = NULL, 
                            reset_token_expiry = NULL 
                        WHERE id = ?
                    ")->execute([$hashed, $user_id]);

                    // Gửi email thông báo
                    $stmt = $pdo->prepare("SELECT email, display_name FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $userInfo = $stmt->fetch();
                    
                    sendPasswordChangedNotification($userInfo['email'], $userInfo['display_name']);

                    unset($_SESSION['reset_user_id']);
                    session_regenerate_id(true);

                    header("Location: login.php?reset=success");
                    exit;
                } else {
                    $message = "<div class='alert alert-danger'>Mã OTP/Link không đúng hoặc đã hết hạn!</div>";
                    $step = 2;
                }
            }
        }
    }
}

// Tạo CSRF Token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu - Note App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f7f6;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card p-4">
                <h3 class="text-center mb-4 text-primary">Khôi phục mật khẩu</h3>

                <?= $message ?>

                <?php if ($step == 1): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="send_reset">

                        <div class="mb-3">
                            <label class="form-label">Nhập email tài khoản</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Chọn phương thức khôi phục:</label><br>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="otp" checked>
                                <label class="form-check-label">Gửi mã OTP</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="link">
                                <label class="form-check-label">Gửi link đặt lại mật khẩu</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Tiếp tục</button>
                    </form>

                <?php elseif ($step == 2): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="reset_password">

                        <?php if (!$token_verified): ?>
                            <div class="mb-3">
                                <label class="form-label">Nhập mã OTP</label>
                                <input type="text" name="otp" class="form-control" maxlength="6" required>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="otp" value="verified_via_link">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Mật khẩu mới</label>
                            <input type="password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Xác nhận mật khẩu mới</label>
                            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Đổi mật khẩu</button>
                    </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">← Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
//----//

service-worker.php
//code//
// ====================== SERVICE WORKER - NOTEAPP PRO ======================
const CACHE_NAME = 'noteapp-v1.7';   // Tăng version khi update lớn

// ====================== INSTALL ======================
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker v1.7...');
    self.skipWaiting();
});

// ====================== ACTIVATE ======================
self.addEventListener('activate', event => {
    console.log('[SW] Activating...');

    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );

    self.clients.claim();
});

// ====================== FETCH ======================
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Bỏ qua WebSocket
    if (url.protocol === 'ws:' || url.protocol === 'wss:') {
        return;
    }

    // Chỉ xử lý request cùng origin
    if (url.origin !== location.origin) {
        return;
    }

    // ==================== API & PHP - NETWORK FIRST ====================
    if (url.pathname.startsWith('/api/') || url.pathname.endsWith('.php')) {
        
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Clone response để cache nếu cần sau này
                    return response;
                })
                .catch(error => {
                    console.warn('[SW] API Fetch Failed (Offline):', error);
                    
                    // Trả về response JSON offline để frontend biết
                    return new Response(
                        JSON.stringify({
                            success: false,
                            message: 'Bạn đang offline. Một số tính năng có thể không hoạt động.',
                            offline: true
                        }),
                        {
                            status: 503,
                            statusText: 'Service Unavailable',
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }

    // ==================== STATIC ASSETS - CACHE FIRST + NETWORK UPDATE ====================
    const isStatic = 
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.webp') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.ico') ||
        url.pathname.endsWith('.json') ||
        url.pathname.endsWith('.manifest');

    if (isStatic) {
        event.respondWith(
            caches.open(CACHE_NAME).then(cache => {
                return cache.match(event.request).then(cachedResponse => {
                    const fetchPromise = fetch(event.request)
                        .then(networkResponse => {
                            if (networkResponse && networkResponse.status === 200) {
                                cache.put(event.request, networkResponse.clone());
                            }
                            return networkResponse;
                        })
                        .catch(() => cachedResponse); // Fallback to cache

                    return cachedResponse || fetchPromise;
                });
            })
        );
        return;
    }

    // Các request khác: Network Only
    event.respondWith(fetch(event.request));
});

// ====================== BACKGROUND SYNC ======================
self.addEventListener('sync', event => {
    if (event.tag === 'sync-notes') {
        console.log('[SW] Background Sync: sync-notes triggered');
        // Có thể implement sync logic sau khi có API sync chuyên dụng
        event.waitUntil(
            // TODO: Sync offline notes khi có kết nối
            Promise.resolve()
        );
    }
});

// ====================== PUSH NOTIFICATION (tương lai) ======================
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
});

console.log('[SW] NoteApp Service Worker v1.7 loaded successfully!');
//----//