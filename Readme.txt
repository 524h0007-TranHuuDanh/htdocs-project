CẤU TRÚC THƯ MỤC:

HTDOCS
|
api - auth_helper.php
|   |
|   -bulk_action.php
|   |
|   - change_color.php
|   |
|   - change_password.php
|   |
|   - change_password.php
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
|   - send_reset_code.php
|   |
|   - set_note_label.php
|   |
|   - share_note.php
|   |
|   - update_avatar.php
|   |
|   - update_display_name.php
|   |
|   - update_preferences.php
|   |
|   - update_profile.php
|   |
|   - upload_image.php
|   |
|   - verify_note.php
|   |
|   - ws_token.php
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
|         |
|         - ws_secret.php
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
    ensure_session_csrf_token();
}

/**
 * Ensures a session-bound CSRF secret exists for authenticated flows.
 */
function ensure_session_csrf_token(): void {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Rejects POST when CSRF token is missing, wrong type, or not hash_equals to session value.
 */
function require_valid_csrf_post(): void {
    $sent = $_POST['csrf_token'] ?? null;
    $sess  = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sess) || $sess === '' || !is_string($sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF).']);
        exit;
    }
    if (!hash_equals($sess, $sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF).']);
        exit;
    }
}
//----//
bulk_action.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$ids = $_POST['ids'] ?? '';
if (!is_array($ids)) {
    $ids = explode(',', $ids);
}
$ids = array_filter(array_map('intval', $ids));
$user_id = $_SESSION['user_id'];

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'Không có ghi chú nào được chọn']);
    exit;
}

try {
    // Kiểm tra quyền sở hữu (chỉ chủ mới bulk xóa/khôi phục/share)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE id IN ($placeholders) AND user_id = ?");
    $checkStmt->execute(array_merge($ids, [$user_id]));
    if ($checkStmt->fetchColumn() != count($ids)) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền với một số ghi chú']);
        exit;
    }

    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã chuyển ' . count($ids) . ' ghi chú vào thùng rác']);
    } 
    elseif ($action === 'restore') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã khôi phục ' . count($ids) . ' ghi chú']);
    }
    elseif ($action === 'permanent') {
        // Xóa vĩnh viễn: cần xóa ảnh vật lý trước
        $imgStmt = $pdo->prepare("SELECT file_path FROM note_images WHERE note_id IN ($placeholders)");
        $imgStmt->execute($ids);
        while ($img = $imgStmt->fetch()) {
            $file = '../' . $img['file_path'];
            if (file_exists($file)) @unlink($file);
        }
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'Đã xóa vĩnh viễn ' . count($ids) . ' ghi chú']);
    }
    elseif ($action === 'share') {
        // Nhận thêm email và permission
        $emails = $_POST['emails'] ?? '';
        $permission = $_POST['permission'] ?? 'read';
        if (empty($emails)) {
            echo json_encode(['success' => false, 'message' => 'Chưa nhập email']);
            exit;
        }
        $emailList = array_unique(array_map('trim', explode(',', $emails)));
        $successCount = 0;
        $messages = [];
        foreach ($ids as $note_id) {
            foreach ($emailList as $email) {
                // Kiểm tra user tồn tại
                $userStmt = $pdo->prepare("SELECT id, email, display_name FROM users WHERE email = ?");
                $userStmt->execute([$email]);
                $receiver = $userStmt->fetch();
                if (!$receiver || $receiver['id'] == $user_id) continue;
                // Upsert shared_notes
                $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
                $check->execute([$note_id, $email]);
                if ($check->fetch()) {
                    $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
                    $update->execute([$permission, $note_id, $email]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)");
                    $insert->execute([$note_id, $user_id, $email, $permission]);
                }
                $successCount++;
                // Gửi mail (có thể gửi riêng từng note, nhưng để tránh spam, gửi một mail tổng hợp? Tạm thời gửi cho mỗi lần share)
                require_once '../mail_config.php';
                $senderName = $_SESSION['display_name'] ?? 'Một người dùng';
                sendShareNotification($email, $receiver['display_name'], $senderName, "Nhiều ghi chú");
            }
        }
        echo json_encode(['success' => true, 'message' => "Đã chia sẻ thành công $successCount lượt"]);
    }
    else {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
//----//
change_color.php
//code//
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
//----//

change_password.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$old = $_POST['old_password'] ?? '';
$new = $_POST['new_password'] ?? '';

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải ≥6 ký tự']);
    exit;
}

$stmt = $pdo->prepare("SELECT password_hash, email, display_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!password_verify($old, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng']);
    exit;
}

$new_hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$new_hash, $_SESSION['user_id']]);

// Gửi email thông báo
sendPasswordChangedNotification($user['email'], $user['display_name']);

echo json_encode(['success' => true]);
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
require_once 'auth_helper.php';
require_once '../database.php';

check_login();   

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
//----//

pin_note.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json; charset=utf-8');

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
require_valid_csrf_post();

header('Content-Type: application/json; charset=utf-8');

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
require_valid_csrf_post();

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
require_valid_csrf_post();

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
            n.title,
            n.content,
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
    // VERSION CHECK + ATOMIC UPDATE (optimistic locking)
    // Client must match current server version; UPDATE only if row still at that version.
    // =========================================================
    $serverVersion = (int) $note['version'];

    if ((int) $client_version !== $serverVersion) {

        echo json_encode([
            'success'          => false,
            'conflict'         => true,
            'version'          => $serverVersion,
            'latest_title'     => $note['title'] ?? '',
            'latest_content'   => $note['content'] ?? '',
            'message'          => 'Có xung đột phiên bản. Đang tải lại ghi chú...'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE notes
        SET
            title = ?,
            content = ?,
            version = version + 1,
            updated_at = NOW()
        WHERE id = ?
          AND version = ?
    ");

    $stmt->execute([
        $title,
        $content,
        $id,
        $serverVersion
    ]);

    if ($stmt->rowCount() === 0) {

        $stmtFresh = $pdo->prepare("
            SELECT version, title, content
            FROM notes
            WHERE id = ?
            LIMIT 1
        ");
        $stmtFresh->execute([$id]);
        $fresh = $stmtFresh->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'success'          => false,
            'conflict'         => true,
            'version'          => (int) ($fresh['version'] ?? $serverVersion),
            'latest_title'     => $fresh['title'] ?? '',
            'latest_content'   => $fresh['content'] ?? '',
            'message'          => 'Ghi chú đã được cập nhật bởi người khác. Đang đồng bộ...'
        ]);

        exit;
    }

    $new_version = $serverVersion + 1;

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
    global $pdo;

    // Ratchet relays typing with DB version per message; verify row matches expected version after save.
    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        return;
    }

    $id       = (int) $note_id;
    $expected = (int) $version;

    if ($id <= 0 || $expected < 1) {
        return;
    }

    $stmt = $pdo->prepare('SELECT version FROM notes WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $actual = (int) $stmt->fetchColumn();

    if ($actual !== $expected) {
        error_log("broadcastNoteUpdate: version mismatch note={$id} expected={$expected} db={$actual}");
    }
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

send_reset_code.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
require_once '../mail_config.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$type = $_POST['type'] ?? 'otp';
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT email, display_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy tài khoản']);
    exit;
}

$reset_token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
    ->execute([$reset_token, $expiry, $user_id]);

if ($type === 'link') {
    $sent = sendResetLinkEmail($user['email'], $user['display_name'], $reset_token);
    $msg = 'Link đặt lại mật khẩu đã được gửi';
} else {
    $otp = rand(100000, 999999);
    $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?")->execute([$otp, $user_id]);
    $sent = sendResetOTPEmail($user['email'], $user['display_name'], $otp);
    $msg = 'Mã OTP đã được gửi';
}

echo json_encode(['success' => $sent, 'message' => $sent ? $msg : 'Gửi email thất bại']);
//----//

set_note_label.php
//code//
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
//----//

share_note.php
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
update_avatar.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Không có file ảnh']);
    exit;
}

$file = $_FILES['avatar'];
$upload_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$max_size = 2 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'Ảnh không được quá 2MB']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận JPG, PNG, GIF, WEBP']);
    exit;
}

$ext_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];
$ext = $ext_map[$mime_type];
$new_filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
$target_path = $upload_dir . $new_filename;
$db_path = 'uploads/avatars/' . $new_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu ảnh']);
    exit;
}

// Xóa avatar cũ (nếu không phải default)
$old = $_SESSION['avatar'] ?? '';
if ($old && $old !== 'uploads/avatars/default-avatar.png' && file_exists(__DIR__ . '/../' . $old)) {
    @unlink(__DIR__ . '/../' . $old);
}

$stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmt->execute([$db_path, $_SESSION['user_id']]);
$_SESSION['avatar'] = $db_path;

echo json_encode(['success' => true, 'avatar' => $db_path]);
//----//

update_display_name.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$new_name = trim($_POST['display_name'] ?? '');
if (strlen($new_name) < 3 || strlen($new_name) > 50) {
    echo json_encode(['success' => false, 'message' => 'Tên phải từ 3-50 ký tự']);
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
$stmt->execute([$new_name, $_SESSION['user_id']]);
$_SESSION['display_name'] = $new_name;
echo json_encode(['success' => true]);
//----//

update_preferences.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

header('Content-Type: application/json');

$note_color = $_POST['note_color'] ?? '#ffffff';
$text_color = $_POST['text_color'] ?? '#0A1024';
$font_family = $_POST['font_family'] ?? 'Inter, system-ui, sans-serif';
$font_size = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';

$stmt = $pdo->prepare("UPDATE users SET note_color = ?, text_color = ?, font_family = ?, font_size = ?, theme_color = ? WHERE id = ?");
$stmt->execute([$note_color, $text_color, $font_family, $font_size, $theme_color, $_SESSION['user_id']]);

$_SESSION['note_color'] = $note_color;
$_SESSION['text_color'] = $text_color;
$_SESSION['font_family'] = $font_family;
$_SESSION['font_size'] = $font_size;
$_SESSION['theme_color'] = $theme_color;

echo json_encode(['success' => true]);
//----//

update_profile.php
//code//
<?php
require_once 'auth_helper.php';
require_once '../database.php';

check_login();
require_valid_csrf_post();   

header('Content-Type: application/json');

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

verify_note.php
//code//
<?php
// api/verify_note.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();
require_valid_csrf_post();

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
WS_token.php
//code//
<?php
/**
 * Short-lived signed token for WebSocket auth (session-bound; not sent with page HTML).
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$cfg    = require __DIR__ . '/../websocket/ws_secret.php';
$secret = $cfg['secret'] ?? '';

if ($secret === '') {
    echo json_encode(['success' => false, 'message' => 'WS chưa cấu hình']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Phiên không hợp lệ']);
    exit;
}

$exp     = time() + 600;
$payload = json_encode(
    [
        'uid'   => $userId,
        'exp'   => $exp,
        'nonce' => bin2hex(random_bytes(8)),
    ],
    JSON_UNESCAPED_SLASHES
);

$sig   = hash_hmac('sha256', $payload, $secret, true);
$token = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=')
    . '.'
    . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

echo json_encode([
    'success' => true,
    'token'   => $token,
    'exp'     => $exp,
]);

//----//

Thư mục App

NoteWebSocket.php
//code//
<?php

namespace App;

use PDO;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {

    protected $clients;
    protected $noteSubscriptions = []; // note_id => [resourceId => ['conn' => conn, 'user_name' => str]]
    /** @var PDO */
    protected $pdo;
    protected $wsSecret;

    public function __construct(PDO $pdo, string $wsSecret) {
        $this->clients   = new \SplObjectStorage;
        $this->pdo       = $pdo;
        $this->wsSecret  = $wsSecret;
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
                $this->handleAuth($from, $data);
                break;
            case 'join_note':
                $this->handleJoinNote($from, $data);
                break;
            case 'leave_note':
                $note_id = intval($data['note_id'] ?? 0);
                $this->removeFromNote($from, $note_id);
                break;
            case 'update':
                $this->handleUpdate($from, $data);
                break;
            // ========== THÊM MỚI: xử lý màu sắc và hình ảnh ==========
            case 'color_update':
                $this->handleColorUpdate($from, $data);
                break;
            case 'image_added':
                $this->handleImageAdded($from, $data);
                break;
            case 'image_deleted':
                $this->handleImageDeleted($from, $data);
                break;
        }
    }

    /**
     * Xác thực token và gán user_id, user_name cho connection.
     */
    private function handleAuth(ConnectionInterface $from, array $data) {
        $token = $data['token'] ?? '';
        if (!is_string($token) || $token === '') {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Thiếu token']));
            return;
        }

        $uid = $this->verifyWsToken($token);
        if ($uid === null) {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Token không hợp lệ hoặc đã hết hạn']));
            return;
        }

        $stmt = $this->pdo->prepare('SELECT id, display_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Người dùng không tồn tại']));
            return;
        }

        $from->user_id   = (int) $row['id'];
        $from->user_name = $row['display_name'] !== '' && $row['display_name'] !== null
            ? $row['display_name']
            : 'User';

        $from->send(json_encode(['type' => 'auth_success']));
        echo "[WS] User {$from->user_id} ({$from->user_name}) authenticated via token\n";
    }

    /**
     * @return int|null user id on success
     */
    private function verifyWsToken(string $token) {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;

        list($payloadB64, $sigB64) = $parts;
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        if ($payloadJson === false || $payloadJson === '') return null;

        $expectedSig = hash_hmac('sha256', $payloadJson, $this->wsSecret, true);
        $sig = base64_decode(strtr($sigB64, '-_', '+/'), true);
        if (!is_string($sig) || !hash_equals($expectedSig, $sig)) return null;

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp'])) return null;

        if ((int) $payload['exp'] < time()) return null;

        return (int) $payload['uid'];
    }

    private function handleJoinNote(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;

        if (!$this->userCanAccessNote((int) $from->user_id, $note_id)) {
            $from->send(json_encode([
                'type'    => 'join_denied',
                'note_id' => $note_id,
                'message' => 'Không có quyền tham gia ghi chú này',
            ]));
            echo "[WS] join_denied user={$from->user_id} note=$note_id\n";
            return;
        }

        $this->noteSubscriptions[$note_id][$from->resourceId] = [
            'conn'      => $from,
            'user_name' => $from->user_name,
        ];
        echo "[WS] User {$from->user_id} joined note $note_id\n";

        $this->broadcastPresence($note_id);
    }

    /**
     * Owner or any active share recipient (same rules as viewing note).
     */
    private function userCanAccessNote(int $userId, int $noteId) {
        $sql = 'SELECT 1 FROM notes n WHERE n.id = ? AND n.is_trashed = 0 AND (
            n.user_id = ?
            OR EXISTS (
                SELECT 1 FROM shared_notes sn
                INNER JOIN users u ON u.id = ? AND sn.recipient_email = u.email
                WHERE sn.note_id = n.id
            )
        ) LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$noteId, $userId, $userId]);
        return (bool) $st->fetchColumn();
    }

    /** Owner or shared with edit permission (matches client realtime for editors). */
    private function userCanEditNote(int $userId, int $noteId) {
        $st = $this->pdo->prepare('SELECT 1 FROM notes WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$noteId, $userId]);
        if ($st->fetchColumn()) return true;

        $st = $this->pdo->prepare(
            "SELECT 1 FROM shared_notes sn
             INNER JOIN users u ON u.id = ? AND sn.recipient_email = u.email
             WHERE sn.note_id = ? AND sn.permission = 'edit' LIMIT 1"
        );
        $st->execute([$userId, $noteId]);
        return (bool) $st->fetchColumn();
    }

    private function fetchAuthoritativeNoteVersion(int $noteId) {
        $st = $this->pdo->prepare('SELECT version FROM notes WHERE id = ? AND is_trashed = 0 LIMIT 1');
        $st->execute([$noteId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : null;
    }

    private function handleUpdate(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || !isset($this->noteSubscriptions[$note_id][$from->resourceId])) return;

        if (!$this->userCanEditNote((int) $from->user_id, $note_id)) return;

        $dbVersion = $this->fetchAuthoritativeNoteVersion($note_id);
        if ($dbVersion === null) return;

        $broadcastData = [
            'type'       => 'update',
            'note_id'    => $note_id,
            'user_name'  => $from->user_name,
            'title'      => $data['title'] ?? null,
            'content'    => $data['content'] ?? null,
            'version'    => $dbVersion,
            'sender_id'  => $from->resourceId,
            'timestamp'  => time(),
        ];

        foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
            if ($info['conn'] !== $from) {
                $info['conn']->send(json_encode($broadcastData));
            }
        }
    }

    /**
     * Broadcast danh sách người đang xem ghi chú
     */
    private function broadcastPresence(int $note_id) {
        if (!isset($this->noteSubscriptions[$note_id])) return;

        $users = array_values(array_map(
            function ($info) { return $info['user_name']; },
            $this->noteSubscriptions[$note_id]
        ));

        $payload = json_encode([
            'type'    => 'presence',
            'note_id' => $note_id,
            'users'   => $users,
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
        foreach (array_keys($this->noteSubscriptions) as $note_id) {
            $this->removeFromNote($conn, (int) $note_id);
        }
        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    // ==================== CÁC PHƯƠNG THỨC MỚI ====================
    /**
     * Broadcast color change to all viewers of the note
     */
    private function handleColorUpdate(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote((int) $from->user_id, $note_id)) return;

        $color = $data['color'] ?? '';

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'color_update',
                'note_id'   => $note_id,
                'color'     => $color,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }

    /**
     * Broadcast image added to all viewers
     */
    private function handleImageAdded(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote($from->user_id, $note_id)) return;

        $image_id = intval($data['image_id'] ?? 0);
        $file_path = $data['file_path'] ?? '';

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'image_added',
                'note_id'   => $note_id,
                'image_id'  => $image_id,
                'file_path' => $file_path,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }

    /**
     * Broadcast image deleted to all viewers
     */
    private function handleImageDeleted(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote($from->user_id, $note_id)) return;

        $image_id = intval($data['image_id'] ?? 0);

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'image_deleted',
                'note_id'   => $note_id,
                'image_id'  => $image_id,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }
}

//----//

Thư mục assets
css
style.css
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
    --note-text-color: #0A1024;
}

/* =========================================================
   RESET & BASE
   ========================================================= */
*, *::before, *::after { box-sizing: border-box; }

html { color-scheme: light dark; font-size: 100%; }

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
   BULK SELECTION CHECKBOX
   ========================================================= */
.note-card .bulk-checkbox {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 15;
    width: 24px;
    height: 24px;
    cursor: pointer;
    background: rgba(255,255,255,0.9);
    border-radius: 8px;
    border: 2px solid var(--primary, #5B9BFF);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: none;
    transition: all 0.2s ease;
}
.bulk-mode .note-card .bulk-checkbox {
    display: block;
}
.note-card .bulk-checkbox:checked {
    background-color: var(--primary, #5B9BFF);
    border-color: var(--primary, #5B9BFF);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z'/%3E%3C/svg%3E");
    background-size: 16px;
    background-position: center;
    background-repeat: no-repeat;
}
.note-card .bulk-checkbox:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    border-color: #aaa;
    filter: grayscale(0.2);
}

/* =========================================================
   BULK TOOLBAR (fixed bottom)
   ========================================================= */
#bulkToolbar {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 1060;
    background: rgba(255,255,255,0.28);
    backdrop-filter: blur(32px) saturate(200%);
    border-radius: 60px;
    padding: 8px 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 1px 0 rgba(255,255,255,0.4) inset;
    border: 1px solid rgba(255,255,255,0.4);
    transition: all 0.3s var(--ease-spring);
}
#bulkToolbar .btn {
    border-radius: 40px !important;
    padding: 6px 16px;
    font-weight: 500;
}
#bulkToolbar .btn-danger {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    border: none;
}
#bulkToolbar .btn-success {
    background: linear-gradient(135deg, #20bf6b, #05c46b);
}
#bulkToolbar .btn-dark {
    background: linear-gradient(135deg, #2d3436, #1e272e);
}
#bulkToolbar .btn-info {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
}
#bulkCounter {
    background: rgba(0,0,0,0.3);
    backdrop-filter: blur(4px);
    border-radius: 40px;
    padding: 2px 12px;
    margin-left: 8px;
}

/* =========================================================
   CARD BODY
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
   DARK MODE - CUSTOM NOTE COLOR CONTRAST FIX
   ========================================================= */
body[data-bs-theme="dark"] .note-card[style*="background"],
body[data-bs-theme="dark"] .note-card[style*="background-color"],
[data-bs-theme="dark"] .note-card[style*="background"],
[data-bs-theme="dark"] .note-card[style*="background-color"] {
    /* Override mọi màu text mặc định của dark mode */
    color: #0A1024 !important;
}

body[data-bs-theme="dark"] .note-card[style*="background"] .card-title,
body[data-bs-theme="dark"] .note-card[style*="background"] .card-text,
body[data-bs-theme="dark"] .note-card[style*="background"] p,
body[data-bs-theme="dark"] .note-card[style*="background"] span,
body[data-bs-theme="dark"] .note-card[style*="background"] small,
[data-bs-theme="dark"] .note-card[style*="background"] .card-title,
[data-bs-theme="dark"] .note-card[style*="background"] .card-text {
    color: #0A1024 !important;
}

/* Cho modal khi có màu nền tùy chỉnh trong dark mode */
body[data-bs-theme="dark"] .modal-content[style*="background"],
body[data-bs-theme="dark"] .modal-content[style*="background-color"],
[data-bs-theme="dark"] .modal-content[style*="background"],
[data-bs-theme="dark"] .modal-content[style*="background-color"] {
    color: #0A1024 !important;
}

body[data-bs-theme="dark"] .modal-content[style*="background"] .modal-title,
body[data-bs-theme="dark"] .modal-content[style*="background"] .modal-body,
body[data-bs-theme="dark"] .modal-content[style*="background"] .form-control,
body[data-bs-theme="dark"] .modal-content[style*="background"] label,
[data-bs-theme="dark"] .modal-content[style*="background"] .modal-title,
[data-bs-theme="dark"] .modal-content[style*="background"] .modal-body {
    color: #0A1024 !important;
}

/* Input trong modal màu nền tùy chỉnh */
body[data-bs-theme="dark"] .modal-content[style*="background"] .form-control,
body[data-bs-theme="dark"] .modal-content[style*="background"] .form-select,
[data-bs-theme="dark"] .modal-content[style*="background"] .form-control,
[data-bs-theme="dark"] .modal-content[style*="background"] .form-select {
    background: rgba(255,255,255,0.85) !important;
    color: #0A1024 !important;
    border-color: rgba(0,0,0,0.2) !important;
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
html {
    font-size: 100%;
}
body {
    font-size: inherit;
}
.note-card, .modal-content, .form-control, .btn,
h1, h2, h3, h4, h5, h6, p, span, div {
    font-size: inherit;
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

/* =========================================================
   NOTE TEXT COLOR - THEME AWARE
   ========================================================= */
.note-card .card-title,
.note-card .card-text,
.modal-content .modal-title,
.modal-content .modal-body,
.modal-content .form-label,
.modal-content p,
.modal-content span:not(.badge):not(.color-btn) {
    color: var(--note-text-color, #0A1024) !important;
}

/* Trong dark mode, giữ nguyên màu chữ custom (đã được set từ inline) */
body[data-bs-theme="dark"] .note-card[style*="background-color"] .card-title,
body[data-bs-theme="dark"] .note-card[style*="background-color"] .card-text {
    color: #0A1024 !important;
}

/* =========================================================
   BULK MODE TOGGLE BUTTON ACTIVE STATE
   ========================================================= */
#toggleBulkModeBtn.active {
    background-color: var(--primary) !important;
    color: white !important;
    border-color: var(--primary) !important;
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
let bulkMode = false;
let bulkShareModal = null;
let selectedNotes = new Set(); // lưu id
let currentNotesList = []; // lưu danh sách note hiện tại để kiểm tra khóa
/** Coalesces list refresh after rapid autosaves (single search request). */
let liveSearchAfterSaveTimer = null;
/** True while applying server title/content+version after HTTP conflict; blocks autosave to avoid stale POSTs. */
let noteConflictResolutionLock = false;
/** True while GET api/get_notes.php (note_id) is in flight for an opened note; blocks autosave until response. */
let noteVersionLoadPending = false;
let noteVersionFetchGen    = 0;

/** Offline queue sync: avoids tight retry loops and exposes status to the UI layer. */
let offlineSyncIsRunning = false;
const OFFLINE_SYNC_BASE_DELAY_MS = 2000;
const OFFLINE_SYNC_MAX_DELAY_MS  = 120000;

// Bootstrap modals (khởi tạo sau DOM ready)
let noteModal           = null;
let customAlertModal    = null;
let customConfirmModal  = null;
let profileSubScreen    = null; // Biến mới cho profile

function resetPasswordModalToDefault() {
    const modal = document.getElementById('passwordModal');
    if (!modal) return;

    const bodyEl = modal.querySelector('.modal-body');
    const footerEl = modal.querySelector('.modal-footer');

    if (!bodyEl || !footerEl) return;

    // Khôi phục body về trạng thái nhập mật khẩu mặc định
    bodyEl.innerHTML = `
        <input type="password" id="notePasswordInput" class="form-control" placeholder="Nhập mật khẩu..." autocomplete="current-password">
        <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
    `;

    // Khôi phục footer về nút mặc định
    footerEl.innerHTML = `
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
        <button type="button" id="passwordModalConfirmBtn" class="btn btn-primary">Xác nhận</button>
    `;

    // Gắn lại sự kiện cho nút xác nhận
    const confirmBtn = document.getElementById('passwordModalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.onclick = submitNotePassword;
    }
}
function appendCsrfToken(formData) {
    formData.append('csrf_token', window.APP_CONFIG?.csrf_token || '');
}

/** application/x-www-form-urlencoded bodies */
function appendCsrfUrlEncoded(body) {
    const raw = window.APP_CONFIG?.csrf_token || '';
    const base = body == null ? '' : String(body);
    const sep    = base.trim() !== '' ? '&' : '';
    return `${base}${sep}csrf_token=${encodeURIComponent(raw)}`;
}

function getNoteContentVersion() {
    const contentEl = document.getElementById('noteContent');
    const raw = contentEl?.dataset.version;
    if (raw === undefined || raw === '') return NaN;
    const v = parseInt(raw, 10);
    return Number.isFinite(v) ? v : NaN;
}

function buildWsNoteUpdatePayload() {
    const titleEl = document.getElementById('noteTitle');
    const contentEl = document.getElementById('noteContent');
    const v = !noteVersionLoadPending ? getNoteContentVersion() : NaN;
    const payload = {
        type:      'update',
        note_id:   currentNoteIdForWS,
        title:     (titleEl && titleEl.value) || '',
        content:   (contentEl && contentEl.value) || '',
        user_name: currentUserName
    };
    if (Number.isFinite(v)) payload.version = v;
    return payload;
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
    bulkShareModal = new bootstrap.Modal(document.getElementById('bulkShareModal'));
    // --- Modals Bootstrap ---
    noteModal          = new bootstrap.Modal(document.getElementById('noteModal'));
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    customAlertModal   = new bootstrap.Modal(document.getElementById('customAlertModal'));
    customConfirmModal = new bootstrap.Modal(document.getElementById('customConfirmModal'));
    const passwordModalEl = document.getElementById('passwordModal');
    if (passwordModalEl) {
        passwordModalEl.addEventListener('hidden.bs.modal', resetPasswordModalToDefault);
    }
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

    // --- Theme preview (đã được thay bằng profile mới, giữ lại applyTheme) ---
    // Không còn themeSelect, fontSelect cũ vì đã chuyển vào profile

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
    // Khôi phục màu nền mặc định từ session nếu có
    const savedNoteColor = localStorage.getItem('noteapp_note_color') || document.documentElement.style.getPropertyValue('--note-default-color');
    if (savedNoteColor) {
        document.documentElement.style.setProperty('--note-default-color', savedNoteColor);
    }
    // Lưu lại khi người dùng thay đổi trong profile (sẽ được xử lý qua savePreferences)
        // Bulk selection buttons
    const toggleBtn = document.getElementById('toggleBulkModeBtn');
    if (toggleBtn) toggleBtn.addEventListener('click', toggleBulkMode);
    
    const bulkDelete = document.getElementById('bulkDeleteBtn');
    const bulkRestore = document.getElementById('bulkRestoreBtn');
    const bulkPermanent = document.getElementById('bulkPermanentBtn');
    const bulkShare = document.getElementById('bulkShareBtn');
    const bulkCancel = document.getElementById('bulkCancelBtn');
    
    if (bulkDelete) bulkDelete.addEventListener('click', () => bulkAction('trash'));
    if (bulkRestore) bulkRestore.addEventListener('click', () => bulkAction('restore'));
    if (bulkPermanent) bulkPermanent.addEventListener('click', () => bulkAction('permanent'));
    if (bulkShare) bulkShare.addEventListener('click', () => bulkAction('share'));
    if (bulkCancel) bulkCancel.addEventListener('click', toggleBulkMode);
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
        if (bulkMode) toggleBulkMode();
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

function scheduleLiveSearchAfterSave() {
    clearTimeout(liveSearchAfterSaveTimer);
    liveSearchAfterSaveTimer = setTimeout(() => {
        liveSearchAfterSaveTimer = null;
        liveSearch();
    }, 500);
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

        let offlineSyncBadge = '';
        if (n.syncStatus === 'error' || n.lastSyncError) {
            offlineSyncBadge = `<span class="badge bg-danger ms-1" title="${escapeHtml(n.lastSyncError || 'Lỗi đồng bộ')}">Chưa đồng bộ</span>`;
        } else if (n.syncStatus === 'pending' && n.nextRetryAt && n.nextRetryAt > Date.now()) {
            offlineSyncBadge = '<span class="badge bg-secondary ms-1" title="Đang chờ thử lại">Chờ thử lại</span>';
        } else if (n.syncStatus === 'pending' || isNoteTemp(n.id)) {
            offlineSyncBadge = '<span class="badge bg-warning text-dark ms-1">Chờ đồng bộ</span>';
        }

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
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'bulk-checkbox';
        checkbox.dataset.id = n.id;
        checkbox.disabled = (n.is_locked == 1); // nếu có mật khẩu
        checkbox.addEventListener('change', (e) => {
            e.stopPropagation();
            if (checkbox.checked) selectedNotes.add(n.id);
            else selectedNotes.delete(n.id);
            updateBulkToolbar();
        });
        card.appendChild(checkbox);
        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                     onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                     <i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1 flex-wrap">
                ${icons} ${escapeHtml(n.title) || 'Không tiêu đề'} ${offlineSyncBadge}
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
// ====================== BULK SELECTION ======================
function updateBulkToolbar() {
    const count = selectedNotes.size;
    const counter = document.getElementById('bulkCounter');
    if (counter) counter.innerText = count;
    
    const isTrash = (currentViewMode === 'trash');
    const deleteBtn = document.getElementById('bulkDeleteBtn');
    const restoreBtn = document.getElementById('bulkRestoreBtn');
    const permanentBtn = document.getElementById('bulkPermanentBtn');
    const shareBtn = document.getElementById('bulkShareBtn');
    
    if (deleteBtn) deleteBtn.style.display = isTrash ? 'none' : 'inline-flex';
    if (restoreBtn) restoreBtn.style.display = isTrash ? 'inline-flex' : 'none';
    if (permanentBtn) permanentBtn.style.display = isTrash ? 'inline-flex' : 'none';
    if (shareBtn) shareBtn.style.display = (currentViewMode === 'my_notes' && !isTrash) ? 'inline-flex' : 'none';
}

function toggleBulkMode() {
    bulkMode = !bulkMode;
    const container = document.getElementById('notesContainer');
    const toolbar = document.getElementById('bulkToolbar');
    const toggleBtn = document.getElementById('toggleBulkModeBtn');
    
    if (bulkMode) {
        container.classList.add('bulk-mode');
        toolbar.classList.remove('d-none');
        toggleBtn.classList.add('active');
        selectedNotes.clear();
        updateBulkToolbar();
    } else {
        container.classList.remove('bulk-mode');
        toolbar.classList.add('d-none');
        toggleBtn.classList.remove('active');
        // Bỏ chọn tất cả checkbox
        document.querySelectorAll('.bulk-checkbox').forEach(cb => {
            cb.checked = false;
        });
        selectedNotes.clear();
    }
}

async function bulkAction(action) {
    if (selectedNotes.size === 0) {
        showToast('Chưa chọn ghi chú nào', 'warning');
        return;
    }
    
    if (action === 'share') {
        // Hiển thị modal nhập email và quyền
        document.getElementById('bulkShareEmails').value = '';
        document.getElementById('bulkSharePermission').value = 'read';
        bulkShareModal.show();
        
        // Xử lý khi nhấn nút xác nhận
        const confirmBtn = document.getElementById('bulkShareConfirmBtn');
        const oldHandler = confirmBtn.onclick;
        confirmBtn.onclick = async () => {
            const emails = document.getElementById('bulkShareEmails').value.trim();
            const permission = document.getElementById('bulkSharePermission').value;
            if (!emails) {
                showToast('Vui lòng nhập email', 'warning');
                return;
            }
            bulkShareModal.hide();
            await executeBulkShare(emails, permission);
        };
        return;
    }
    
    // Các action khác (trash, restore, permanent) dùng confirm cũ
    let confirmMsg = '';
    if (action === 'trash') {
        confirmMsg = `Bạn có chắc muốn chuyển ${selectedNotes.size} ghi chú vào thùng rác?`;
    } else if (action === 'restore') {
        confirmMsg = `Khôi phục ${selectedNotes.size} ghi chú?`;
    } else if (action === 'permanent') {
        confirmMsg = `Xóa vĩnh viễn ${selectedNotes.size} ghi chú? Hành động này không thể hoàn tác.`;
    } else {
        return;
    }
    
    showConfirm(confirmMsg, () => executeBulkAction(action));
}

async function executeBulkShare(emails, permission) {
    const fd = new FormData();
    fd.append('action', 'share');
    fd.append('ids', Array.from(selectedNotes).join(','));
    fd.append('emails', emails);
    fd.append('permission', permission);
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/bulk_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            toggleBulkMode();
            liveSearch();
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}

async function executeBulkAction(action) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('ids', Array.from(selectedNotes).join(','));
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/bulk_action.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message, 'success');
            toggleBulkMode();
            liveSearch();
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}
// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    // Kiểm tra modal tồn tại
    const noteIdInput = document.getElementById('noteId');
    if (!noteIdInput) {
        console.error('Modal chưa sẵn sàng. Reload trang...');
        location.reload();
        return;
    }

    currentNoteId = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        // Reset modal password về trạng thái mặc định TRƯỚC KHI SHOW
        resetPasswordModalToDefault();

        const modalTitle = document.getElementById('passwordModalTitle');
        if (modalTitle) modalTitle.textContent = '🔒 Ghi chú đã bị khóa';

        const pwdInput = document.getElementById('notePasswordInput');
        if (pwdInput) pwdInput.value = '';

        const errorEl = document.getElementById('passwordError');
        if (errorEl) errorEl.style.display = 'none';

        window.tempOpenData = { id, title, content, color, permission, ownerName };
        passwordModalInstance.show();

        setTimeout(() => {
            const input = document.getElementById('notePasswordInput');
            if (input) input.focus();
        }, 500);
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    let pwdInput = document.getElementById('notePasswordInput');
    if (!pwdInput) {
        console.error('[submitNotePassword] Input mật khẩu không tồn tại! Khôi phục modal...');
        resetPasswordModalToDefault();
        pwdInput = document.getElementById('notePasswordInput');
        if (!pwdInput) {
            showAlert('Lỗi giao diện, vui lòng tải lại trang.', 'danger');
            return;
        }
    }

    const password = pwdInput.value.trim();
    const errorEl = document.getElementById('passwordError');
    if (!password) {
        if (errorEl) {
            errorEl.textContent = 'Vui lòng nhập mật khẩu!';
            errorEl.style.display = 'block';
        }
        pwdInput.focus();
        return;
    }

    const fd = new FormData();
    fd.append('note_id', currentNoteId);
    fd.append('password', password);
    appendCsrfToken(fd);

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
                if (errorEl) {
                    errorEl.textContent = d.message || 'Mật khẩu không đúng!';
                    errorEl.style.display = 'block';
                }
                pwdInput.value = '';
                pwdInput.focus();
            }
        })
        .catch(() => {
            if (errorEl) {
                errorEl.textContent = 'Lỗi kết nối!';
                errorEl.style.display = 'block';
            }
        });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    currentNoteId     = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl = document.getElementById('noteContent');
    delete contentEl.dataset.version;

    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText            = '';
    lastAutoSavePersistSig = '';
    noteConflictResolutionLock = false;
    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    clearTimeout(liveSearchAfterSaveTimer);
    clearTimeout(realtimeTypingTimer);
    liveSearchAfterSaveTimer = null;
    autoSaveTimer          = null;
    autoSaveRetryTimer     = null;
    autoSaveBusyTimer      = null;
    realtimeTypingTimer    = null;
    autoSaveInFlightSeq++;

    if (id) {
        noteVersionLoadPending = true;
        const fetchGen = ++noteVersionFetchGen;
        fetch(`api/get_notes.php?note_id=${id}`)
            .then(r => r.json())
            .then(note => {
                if (note != null && note.version != null && note.version !== '') {
                    contentEl.dataset.version = String(note.version);
                }
            })
            .catch(() => {})
            .finally(() => {
                if (fetchGen === noteVersionFetchGen) {
                    noteVersionLoadPending = false;
                }
            });
    } else {
        noteVersionFetchGen++;
        noteVersionLoadPending = false;
    }

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
    clearTimeout(liveSearchAfterSaveTimer);
    liveSearchAfterSaveTimer = null;
    stopRealtime();
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
function _autoSavePayloadSignature() {
    const noteId  = document.getElementById('noteId').value;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;
    return `${noteId}\x1e${title}\x1e${content}\x1e${getNoteContentVersion()}`;
}

function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    if (window.__remoteUpdating) return;
    if (noteConflictResolutionLock) return;

    const noteId  = document.getElementById('noteId').value;
    if (noteId && noteVersionLoadPending) return;
    const title   = document.getElementById('noteTitle').value.trim();
    const content = document.getElementById('noteContent').value;

    if (!noteId && !title && !content) return;

    const hadPendingDebounce = !!autoSaveTimer;
    clearTimeout(autoSaveTimer);
    clearTimeout(autoSaveRetryTimer);
    clearTimeout(autoSaveBusyTimer);
    autoSaveRetryTimer = null;
    autoSaveBusyTimer  = null;

    if (!hadPendingDebounce) {
        document.getElementById('saveStatus').innerHTML = '<i class="bi bi-hourglass-split"></i> Đang lưu...';
    }

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

            if (!nid && !tit && !cont) {
                document.getElementById('saveStatus').innerText = '';
                return;
            }

            if (nid && noteVersionLoadPending) return;

            if (nid && _autoSavePayloadSignature() === lastAutoSavePersistSig) {
                const statusEl = document.getElementById('saveStatus');
                statusEl.innerHTML = lastAutoSavePersistSig
                    ? '<i class="bi bi-check-circle-fill text-success"></i> Đã lưu'
                    : '';
                return;
            }

            // --- OFFLINE: lưu cục bộ ngay lập tức ---
            if (!navigator.onLine) {
                const vOff = getNoteContentVersion();
                const noteData = {
                    id:         nid || 'temp_' + Date.now(),
                    title:      tit,
                    content:    cont,
                    version:    Number.isFinite(vOff) ? vOff : 1,
                    updated_at: new Date().toISOString()
                };
                saveNoteOffline(noteData);
                document.getElementById('saveStatus').innerHTML =
                    '<span class="text-warning"><i class="bi bi-cloud-slash"></i> Đã lưu offline</span>';
                showToast('Không có mạng. Ghi chú đã lưu cục bộ.', 'warning');
                return;
            }

            // --- ONLINE: gửi lên server ---
            const verNum = getNoteContentVersion();
            if (nid && !Number.isFinite(verNum)) {
                document.getElementById('saveStatus').innerText = '';
                return;
            }

            isSaving = true;
            const mySeq = ++autoSaveInFlightSeq;

            const fd = new FormData();
            fd.append('id',      nid);
            fd.append('title',   tit);
            fd.append('content', cont);
            fd.append('version', nid ? String(verNum) : '1');
            appendCsrfToken(fd);

            fetch('api/save_note.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(d => {
                    if (mySeq !== autoSaveInFlightSeq) return;

                    const statusEl  = document.getElementById('saveStatus');
                    const contentEl = document.getElementById('noteContent');

                    if (!d.success && d.conflict) {
                        clearTimeout(autoSaveRetryTimer);
                        autoSaveRetryTimer = null;

                        const titleEl = document.getElementById('noteTitle');
                        const hasLatestTitle   = d.latest_title !== undefined;
                        const hasLatestContent = d.latest_content !== undefined;

                        noteConflictResolutionLock = true;
                        window.__remoteUpdating = true;
                        try {
                            if (hasLatestTitle) {
                                titleEl.value = d.latest_title;
                            }
                            if (hasLatestContent) {
                                contentEl.value = d.latest_content;
                            }
                            if (hasLatestTitle && hasLatestContent &&
                                d.version !== undefined && d.version !== null && d.version !== '') {
                                contentEl.dataset.version = String(d.version);
                            }
                            lastAutoSavePersistSig = _autoSavePayloadSignature();
                        } finally {
                            window.__remoteUpdating = false;
                            noteConflictResolutionLock = false;
                        }

                        statusEl.innerHTML = '<i class="bi bi-arrow-clockwise text-warning"></i> Đồng bộ từ máy chủ';
                        showToast(
                            'Nội dung đã được đồng bộ với bản mới nhất trên máy chủ. Tiếp tục chỉnh sửa để lưu.',
                            'warning'
                        );
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
                        scheduleLiveSearchAfterSave();
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
                        version:    Number.isFinite(getNoteContentVersion()) ? getNoteContentVersion() : 1,
                        updated_at: new Date().toISOString()
                    };
                    saveNoteOffline(noteData);
                    showToast('Không kết nối mạng. Ghi chú đã được lưu cục bộ.', 'warning');
                })
                .finally(() => {
                    if (mySeq === autoSaveInFlightSeq) {
                        isSaving = false;
                    }
                    if (mySeq !== autoSaveInFlightSeq) return;
                    setTimeout(() => {
                        if (isSaving || autoSaveTimer) return;
                        if (currentViewMode === 'trash' || currentPermission === 'read') return;
                        if (noteVersionLoadPending) return;
                        if (noteConflictResolutionLock) return;
                        if (!navigator.onLine) return;
                        if (_autoSavePayloadSignature() !== lastAutoSavePersistSig) {
                            autoSave();
                        }
                    }, 0);
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
        appendCsrfToken(fd);
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
    const titleEl = document.getElementById('passwordModalTitle');
    const bodyEl = document.getElementById('passwordModal').querySelector('.modal-body');
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
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        footerEl.querySelectorAll('.pm-action-btn').forEach(b => {
            b.disabled = false;
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
                .then(shouldClose => {
                    if (shouldClose === true) {
                        passwordModalInstance.hide();
                        resetPasswordModalToDefault();
                        liveSearch();
                    }
                })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    const modalEl = document.getElementById('passwordModal');
    const onHidden = () => {
        resetPasswordModalToDefault();
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
    };
    modalEl.addEventListener('hidden.bs.modal', onHidden);

    passwordModalInstance.show();

    setTimeout(() => {
        const firstField = document.getElementById(config.fields[0]?.id);
        if (firstField) firstField.focus();
    }, 100);
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
    appendCsrfToken(fd);
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

    if (!currentUserId) {
        console.warn('WebSocket: chưa đăng nhập, dùng fallback polling.');
        _startFallbackPolling();
        return;
    }

    fetch('api/ws_token.php', { credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : Promise.reject()))
        .then((d) => {
            if (!d.success || !d.token) return Promise.reject(new Error('ws_token'));
            return d.token;
        })
        .then((token) => {
            let socket;
            try {
                ws = new WebSocket(WS_HOST);
                socket = ws;
            } catch (e) {
                console.warn('WebSocket không khả dụng, dùng fallback polling.');
                _startFallbackPolling();
                return;
            }

            socket.onopen = () => {
                if (ws !== socket) return;
                clearTimeout(wsReconnectTimer);
                wsReconnectTimer = null;
                _stopFallbackPolling();
                socket.send(JSON.stringify({ type: 'auth', token }));
                _setWsStatus('connecting');
            };

            socket.onmessage = (event) => {
                if (ws !== socket) return;
                try {
                    const data = JSON.parse(event.data);

                    if (data.type === 'auth_error') {
                        wsReady = false;
                        _setWsStatus('offline');
                        try { socket.close(); } catch (e2) {}
                        return;
                    }

                    if (data.type === 'join_denied' && data.note_id == currentNoteIdForWS) {
                        console.warn('[WS] join_denied:', data.message || '');
                        return;
                    }

                    if (data.type === 'auth_success') {
                        wsReady = true;
                        _setWsStatus('online');
                        if (currentNoteIdForWS) _wsSend({ type: 'join_note', note_id: currentNoteIdForWS });
                    }

                    if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                        if (data.user_name === currentUserName) return;

                        const contentElPre = document.getElementById('noteContent');
                        const incomingVer = data.version != null && data.version !== ''
                            ? parseInt(data.version, 10)
                            : NaN;
                        const rawLocal = contentElPre && contentElPre.dataset.version;
                        const localVer = rawLocal !== undefined && rawLocal !== ''
                            ? parseInt(rawLocal, 10)
                            : NaN;
                        if (Number.isFinite(incomingVer) && Number.isFinite(localVer) && incomingVer < localVer) {
                            return;
                        }

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
                            if (data.version != null && data.version !== '') {
                                contentEl.dataset.version = String(data.version);
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
                                        try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e3) {}
                                    }
                                }
                            }
                        } finally {
                            window.__remoteUpdating = false;
                        }

                        _showTypingIndicator(data.user_name);
                    }

                    // ========== XỬ LÝ MÀU SẮC ==========
                    if (data.type === 'color_update' && data.note_id == currentNoteIdForWS) {
                        const modalWrapper = document.getElementById('modalContentWrapper');
                        if (modalWrapper) {
                            modalWrapper.style.backgroundColor = data.color;
                            modalWrapper.style.setProperty('--note-individual-color', data.color);
                        }
                        const cards = document.querySelectorAll('.note-card');
                        cards.forEach(card => {
                            const body = card.querySelector('.card-body');
                            if (body && body.dataset.id == data.note_id) {
                                card.style.backgroundColor = data.color;
                            }
                        });
                        _showTypingIndicator(data.user_name + ' đã đổi màu');
                    }

                    // ========== XỬ LÝ THÊM ẢNH ==========
                    if (data.type === 'image_added' && data.note_id == currentNoteIdForWS) {
                        renderImage(data.file_path, data.image_id, currentPermission);
                        _showTypingIndicator(data.user_name + ' đã thêm ảnh');
                    }

                    // ========== XỬ LÝ XÓA ẢNH ==========
                    if (data.type === 'image_deleted' && data.note_id == currentNoteIdForWS) {
                        const imgDiv = document.querySelector(`#imagePreviewContainer [data-image-id="${data.image_id}"]`);
                        if (imgDiv) imgDiv.remove();
                        _showTypingIndicator(data.user_name + ' đã xóa ảnh');
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
        })
        .catch(() => {
            console.warn('WebSocket không lấy được token, dùng fallback polling.');
            _startFallbackPolling();
        });
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
    const f = document.getElementById('imageInput').files[0];
    if (!f) return;

    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    appendCsrfToken(fd);

    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
                if (wsReady && currentNoteIdForWS == nid) {
                    _wsSend({
                        type: 'image_added',
                        note_id: nid,
                        image_id: d.image_id,
                        file_path: d.file_path,
                        user_name: currentUserName
                    });
                }
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
        `<div class="position-relative shadow-sm rounded" data-image-id="${id}">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;" data-id="${id}">${del}
         </div>`;
}

function deleteImage(id, btn) {
    showConfirm('Xóa ảnh này?', () => {
        fetch('api/delete_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(r => r.json()).then(d => {
            if (d.success) {
                const imgDiv = btn.closest('[data-image-id]');
                if (imgDiv) imgDiv.remove();
                if (wsReady && currentNoteIdForWS) {
                    _wsSend({
                        type: 'image_deleted',
                        note_id: currentNoteIdForWS,
                        image_id: id,
                        user_name: currentUserName
                    });
                }
            }
        });
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
        body:    appendCsrfUrlEncoded(`name=${encodeURIComponent(name)}`)
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    appendCsrfUrlEncoded(`id=${id}&name=${encodeURIComponent(newName.trim())}`)
    }).then(() => loadFilterLabels(() => liveSearch()));
}

function deleteLabel(id) {
    showConfirm('Xóa nhãn này?', () => {
        fetch('api/manage_labels.php?action=delete', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    appendCsrfUrlEncoded(`id=${id}`)
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
        body:    appendCsrfUrlEncoded(`note_id=${nid}&label_id=${lid}&action=add`)
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
        body:    appendCsrfUrlEncoded(`note_id=${nid}&label_id=${lid}&action=remove`)
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== PROFILE / SETTINGS (MỚI) ======================
function showProfileModal() {
    profileSubScreen = 'main';
    renderProfileScreen();
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
}

function renderProfileScreen() {
    const titleEl = document.getElementById('profileModalTitle');
    const bodyEl = document.getElementById('profileModalBody');
    const cfg = window.APP_CONFIG || {};

    if (profileSubScreen === 'main') {
        titleEl.innerHTML = '<i class="bi bi-person-circle"></i> Tài khoản & Tùy chỉnh';
        bodyEl.innerHTML = `
            <div class="d-grid gap-3">
                <button class="btn btn-outline-primary btn-lg py-3" onclick="profileGoTo('account')">
                    <i class="bi bi-gear-wide-connected fs-4 me-2"></i> Cài đặt tài khoản
                </button>
                <button class="btn btn-outline-secondary btn-lg py-3" onclick="profileGoTo('preferences')">
                    <i class="bi bi-palette fs-4 me-2"></i> Tùy chỉnh ghi chú
                </button>
            </div>
            <div class="mt-4 text-center">
                <img src="${escapeHtml(cfg.avatar || 'uploads/avatars/default-avatar.png')}" 
                     class="rounded-circle border shadow-sm" style="width:64px;height:64px;object-fit:cover;">
                <div class="mt-2">
                    <strong>${escapeHtml(cfg.displayName || 'Người dùng')}</strong><br>
                    <small class="text-muted">${escapeHtml(cfg.email || '')}</small>
                </div>
            </div>
        `;
    } 
    else if (profileSubScreen === 'account') {
        titleEl.innerHTML = '<i class="bi bi-arrow-left me-2" style="cursor:pointer;" onclick="profileGoTo(\'main\')"></i> Cài đặt tài khoản';
        bodyEl.innerHTML = `
            <form id="accountSettingsForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" class="form-control" value="${escapeHtml(cfg.email || '')}" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tên hiển thị</label>
                    <input type="text" id="displayNameInput" class="form-control" value="${escapeHtml(cfg.displayName || '')}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Đổi mật khẩu</label>
                    <input type="password" id="oldPassword" class="form-control mb-2" placeholder="Mật khẩu hiện tại">
                    <input type="password" id="newPassword" class="form-control mb-2" placeholder="Mật khẩu mới (≥6 ký tự)">
                    <input type="password" id="confirmPassword" class="form-control" placeholder="Xác nhận mật khẩu mới">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ảnh đại diện</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="previewAvatarAccount" src="${escapeHtml(cfg.avatar || 'uploads/avatars/default-avatar.png')}" 
                             style="width:60px;height:60px;object-fit:cover;border-radius:50%;">
                        <label class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-camera"></i> Chọn ảnh
                            <input type="file" id="avatarFileInput" hidden accept="image/*" onchange="previewAvatarAccount(this)">
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Quên mật khẩu?</span>
                        <button type="button" class="btn btn-link p-0" onclick="showForgotPasswordModal()">Gửi OTP / Link</button>
                    </div>
                </div>
                <button type="button" class="btn btn-success w-100" onclick="saveAccountSettings()">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
            </form>
        `;
    }
    else if (profileSubScreen === 'preferences') {
        titleEl.innerHTML = '<i class="bi bi-arrow-left me-2" style="cursor:pointer;" onclick="profileGoTo(\'main\')"></i> Tùy chỉnh ghi chú';
        const currentFontSize = cfg.fontSize || '16px';
        const currentTheme = cfg.theme || 'light';
        const currentNoteColor = cfg.noteColor || '#ffffff';
        const currentTextColor = cfg.textColor || '#0A1024';
        const currentFontFamily = cfg.fontFamily || 'Inter, system-ui, sans-serif';
        
        bodyEl.innerHTML = `
            <form id="preferencesForm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Màu ghi chú mặc định</label>
                    <input type="color" id="prefNoteColor" class="form-control form-control-color" value="${currentNoteColor}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Màu chữ (ghi chú)</label>
                    <input type="color" id="prefTextColor" class="form-control form-control-color" value="${currentTextColor}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phông chữ</label>
                    <select id="prefFontFamily" class="form-select">
                        <option value="Inter, system-ui, sans-serif" ${currentFontFamily.includes('Inter') ? 'selected' : ''}>Inter (Mặc định)</option>
                        <option value="'Roboto', sans-serif" ${currentFontFamily.includes('Roboto') ? 'selected' : ''}>Roboto</option>
                        <option value="'Poppins', sans-serif" ${currentFontFamily.includes('Poppins') ? 'selected' : ''}>Poppins</option>
                        <option value="'Open Sans', sans-serif" ${currentFontFamily.includes('Open Sans') ? 'selected' : ''}>Open Sans</option>
                        <option value="'Lato', sans-serif" ${currentFontFamily.includes('Lato') ? 'selected' : ''}>Lato</option>
                        <option value="'Montserrat', sans-serif" ${currentFontFamily.includes('Montserrat') ? 'selected' : ''}>Montserrat</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Kích cỡ chữ</label>
                    <select id="prefFontSize" class="form-select">
                        <option value="14px" ${currentFontSize === '14px' ? 'selected' : ''}>Nhỏ (14px)</option>
                        <option value="16px" ${currentFontSize === '16px' ? 'selected' : ''}>Vừa (16px)</option>
                        <option value="18px" ${currentFontSize === '18px' ? 'selected' : ''}>Lớn (18px)</option>
                        <option value="20px" ${currentFontSize === '20px' ? 'selected' : ''}>Siêu lớn (20px)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Giao diện (Theme)</label>
                    <select id="prefTheme" class="form-select">
                        <option value="light" ${currentTheme === 'light' ? 'selected' : ''}>Sáng</option>
                        <option value="dark" ${currentTheme === 'dark' ? 'selected' : ''}>Tối</option>
                    </select>
                </div>
                <button type="button" class="btn btn-success w-100" onclick="savePreferences()">
                    <i class="bi bi-save"></i> Lưu thay đổi
                </button>
            </form>
        `;
    }
}

function profileGoTo(screen) {
    profileSubScreen = screen;
    renderProfileScreen();
}

function previewAvatarAccount(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatarAccount').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

async function saveAccountSettings() {
    const newDisplayName = document.getElementById('displayNameInput').value.trim();
    const avatarFile = document.getElementById('avatarFileInput').files[0];
    const oldPwd = document.getElementById('oldPassword').value;
    const newPwd = document.getElementById('newPassword').value;
    const confirmPwd = document.getElementById('confirmPassword').value;
    
    // 1. Cập nhật tên hiển thị
    if (newDisplayName && newDisplayName !== window.APP_CONFIG?.displayName) {
        const fd = new FormData();
        fd.append('display_name', newDisplayName);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/update_display_name.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) showToast(data.message || 'Lỗi cập nhật tên', 'danger');
            else {
                window.APP_CONFIG.displayName = newDisplayName;
                showToast('Cập nhật tên thành công', 'success');
            }
        } catch(e) { showToast('Lỗi kết nối khi đổi tên', 'danger'); }
    }
    
    // 2. Đổi mật khẩu
    if (oldPwd || newPwd || confirmPwd) {
        if (!oldPwd || !newPwd || !confirmPwd) {
            showToast('Vui lòng nhập đầy đủ mật khẩu cũ và mới', 'warning');
            return;
        }
        if (newPwd.length < 6) {
            showToast('Mật khẩu mới phải có ít nhất 6 ký tự', 'warning');
            return;
        }
        if (newPwd !== confirmPwd) {
            showToast('Mật khẩu xác nhận không khớp', 'warning');
            return;
        }
        const fd = new FormData();
        fd.append('old_password', oldPwd);
        fd.append('new_password', newPwd);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/change_password.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (!data.success) showToast(data.message, 'danger');
            else showToast('Đổi mật khẩu thành công', 'success');
        } catch(e) { showToast('Lỗi kết nối', 'danger'); }
    }
    
    // 3. Upload avatar
    if (avatarFile) {
        const fd = new FormData();
        fd.append('avatar', avatarFile);
        appendCsrfToken(fd);
        try {
            const res = await fetch('api/update_avatar.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.APP_CONFIG.avatar = data.avatar;
                document.querySelector('.nav-avatar').src = data.avatar + '?v=' + Date.now();
                showToast('Cập nhật avatar thành công', 'success');
            } else showToast(data.message, 'danger');
        } catch(e) { showToast('Lỗi upload ảnh', 'danger'); }
    }
    
    // Refresh lại modal để cập nhật thông tin
    setTimeout(() => renderProfileScreen(), 500);
}

async function savePreferences() {
    const noteColor = document.getElementById('prefNoteColor').value;
    const textColor = document.getElementById('prefTextColor').value;
    const fontFamily = document.getElementById('prefFontFamily').value;
    const fontSize = document.getElementById('prefFontSize').value;
    const theme = document.getElementById('prefTheme').value;
    
    const fd = new FormData();
    fd.append('note_color', noteColor);
    fd.append('text_color', textColor);
    fd.append('font_family', fontFamily);
    fd.append('font_size', fontSize);
    fd.append('theme_color', theme);
    appendCsrfToken(fd);
    
    try {
        const res = await fetch('api/update_preferences.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            // Áp dụng màu nền mặc định
            document.documentElement.style.setProperty('--note-default-color', noteColor);
            // Áp dụng màu chữ
            document.documentElement.style.setProperty('--note-text-color', textColor);
            // Áp dụng font chữ
            document.body.style.fontFamily = fontFamily;
            // Áp dụng cỡ chữ - set vào html để tránh bị CSS ghi đè
            document.documentElement.style.fontSize = fontSize;
            document.body.style.fontSize = fontSize; // fallback
            // Áp dụng theme
            applyTheme(theme);
            
            // Cập nhật tất cả các note card hiện có để đồng bộ giao diện
            document.querySelectorAll('.note-card').forEach(card => {
                // Đảm bảo font-size được kế thừa từ html
                card.style.fontSize = '';
                // Cập nhật màu chữ nếu card chưa có màu nền riêng
                const titleEl = card.querySelector('.card-title');
                const textEl = card.querySelector('.card-text');
                if (titleEl && !card.style.backgroundColor) titleEl.style.color = textColor;
                if (textEl && !card.style.backgroundColor) textEl.style.color = textColor;
            });
            
            // Cập nhật modal nếu đang mở
            const modalContent = document.getElementById('modalContentWrapper');
            if (modalContent && getComputedStyle(modalContent).backgroundColor !== 'rgba(0, 0, 0, 0)') {
                modalContent.style.setProperty('--note-text-color', textColor);
                const modalTexts = modalContent.querySelectorAll('.modal-title, .modal-body, .form-label, p, span:not(.badge)');
                modalTexts.forEach(el => el.style.color = textColor);
            }
            
            // Cập nhật APP_CONFIG
            window.APP_CONFIG.noteColor = noteColor;
            window.APP_CONFIG.textColor = textColor;
            window.APP_CONFIG.fontFamily = fontFamily;
            window.APP_CONFIG.fontSize = fontSize;
            window.APP_CONFIG.theme = theme;
            
            showToast('Đã lưu tùy chỉnh ghi chú', 'success');
            profileGoTo('main');
        } else {
            showToast(data.message || 'Lỗi lưu cài đặt', 'danger');
        }
    } catch(e) {
        showToast('Lỗi kết nối', 'danger');
    }
}

function showForgotPasswordModal() {
    showConfirm('Bạn muốn nhận OTP hay link reset qua email?', 
        () => sendResetRequest('otp'),
        () => sendResetRequest('link')
    );
}

async function sendResetRequest(type) {
    const email = window.APP_CONFIG?.email;
    if (!email) return;
    const fd = new FormData();
    fd.append('email', email);
    fd.append('type', type);
    appendCsrfToken(fd);
    try {
        const res = await fetch('api/send_reset_code.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(`Đã gửi ${type === 'otp' ? 'mã OTP' : 'link reset'} đến email của bạn`, 'success');
        } else {
            showToast(data.message, 'danger');
        }
    } catch(e) { showToast('Lỗi gửi yêu cầu', 'danger'); }
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
    const fd = new FormData();
    fd.append('id', id);
    fd.append('is_pinned', state);
    appendCsrfToken(fd);
    fetch('api/pin_note.php', { method: 'POST', body: fd })
        .then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) {
        showAlert('Vui lòng lưu ghi chú trước khi đổi màu!', 'warning');
        return;
    }

    const fd = new FormData();
    fd.append('id', id);
    fd.append('color', color || '');
    appendCsrfToken(fd);

    const modalWrapper = document.getElementById('modalContentWrapper');
    if (modalWrapper) {
        modalWrapper.style.backgroundColor = color || '';
        modalWrapper.style.setProperty('--note-individual-color', color || '');
    }

    if (!navigator.onLine) {
        queueAction('api/change_color.php', fd);
        showToast('Đã lưu thay đổi màu (offline)', 'success');
        liveSearch();
        return;
    }

    fetch('api/change_color.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                showToast('Đã đổi màu ghi chú thành công', 'success');
                if (wsReady && currentNoteIdForWS == id) {
                    _wsSend({
                        type: 'color_update',
                        note_id: id,
                        color: color || '',
                        user_name: currentUserName
                    });
                }
                liveSearch();
            } else {
                showAlert(d.message || 'Không thể đổi màu!', 'danger');
                if (modalWrapper) {
                    modalWrapper.style.backgroundColor = '';
                    modalWrapper.style.removeProperty('--note-individual-color');
                }
            }
        })
        .catch(() => {
            showAlert('Lỗi kết nối, vui lòng thử lại!', 'danger');
            if (modalWrapper) {
                modalWrapper.style.backgroundColor = '';
                modalWrapper.style.removeProperty('--note-individual-color');
            }
        });
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
        scheduleOfflineSyncStateEvent();
        if (navigator.onLine) {
            setTimeout(syncOfflineNotes, 2000);
        }
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
        isTemp:        isNoteTemp(note.id),
        syncStatus:    'pending',
        updated_at:    new Date().toISOString(),
        retryCount:    0,
        nextRetryAt:   undefined,
        lastSyncError: undefined
    };
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(noteToSave);
    console.log(`[Offline] Saved note ${note.id}`);
    scheduleOfflineSyncStateEvent();
}

function putOfflineNote(note) {
    if (!db) return;
    const tx = db.transaction(['notes'], 'readwrite');
    tx.objectStore('notes').put(note);
}

async function bumpOfflineRetry(note, message) {
    const retry = (note.retryCount || 0) + 1;
    const exp   = Math.min(6, Math.max(0, retry - 1));
    const delay = Math.min(OFFLINE_SYNC_MAX_DELAY_MS, OFFLINE_SYNC_BASE_DELAY_MS * Math.pow(2, exp));
    putOfflineNote({
        ...note,
        syncStatus:    'error',
        retryCount:    retry,
        nextRetryAt:   Date.now() + delay,
        lastSyncError: typeof message === 'string' ? message : 'Lỗi đồng bộ'
    });
    scheduleOfflineSyncRetrySweep();
}

let offlineSyncSweepTimer = null;

function scheduleOfflineSyncRetrySweep() {
    clearTimeout(offlineSyncSweepTimer);
    offlineSyncSweepTimer = null;
    if (!navigator.onLine || !db) return;
    setTimeout(() => {
        getAllOfflineNotes().then((notes) => {
            const now = Date.now();
            let minWait = null;
            for (const n of notes) {
                if (n.nextRetryAt && n.nextRetryAt > now) {
                    const w = n.nextRetryAt - now + 300;
                    if (minWait === null || w < minWait) minWait = w;
                }
            }
            if (minWait == null) return;
            offlineSyncSweepTimer = setTimeout(() => {
                offlineSyncSweepTimer = null;
                if (navigator.onLine) syncOfflineNotes();
            }, minWait);
        });
    }, 0);
}

function scheduleOfflineSyncStateEvent() {
    if (typeof queueMicrotask === 'function') {
        queueMicrotask(() => { emitOfflineSyncStateEvent(); });
    } else {
        setTimeout(() => { emitOfflineSyncStateEvent(); }, 0);
    }
}

async function getOfflineSyncSummary() {
    const notes = await getAllOfflineNotes();
    const now   = Date.now();
    let errorCount = 0;
    let backoffCount = 0;
    for (const n of notes) {
        if (n.syncStatus === 'error') errorCount++;
        if (n.nextRetryAt && n.nextRetryAt > now) backoffCount++;
    }
    return {
        total:          notes.length,
        errorCount,
        backoffCount,
        readyCount:     Math.max(0, notes.length - backoffCount),
        syncing:        offlineSyncIsRunning
    };
}

function emitOfflineSyncStateEvent() {
    getOfflineSyncSummary().then((detail) => {
        try {
            window.dispatchEvent(new CustomEvent('noteapp:offline-sync', { detail }));
        } catch (e) { /* ignore */ }
    });
}

if (typeof window !== 'undefined') {
    window.getNoteAppOfflineSyncSummary = getOfflineSyncSummary;
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

    const now        = Date.now();
    const toProcess  = offlineNotes.filter((n) => !n.nextRetryAt || n.nextRetryAt <= now);
    if (toProcess.length === 0) {
        scheduleOfflineSyncStateEvent();
        scheduleOfflineSyncRetrySweep();
        return;
    }

    console.log(`[Offline Sync] Found ${offlineNotes.length} notes (${toProcess.length} due now)`);
    offlineSyncIsRunning = true;
    emitOfflineSyncStateEvent();

    let syncedCount              = 0;
    let conflictStillPending    = 0;

    for (const note of toProcess) {
        let work               = { ...note };
        let unresolvedConflict = false;

        for (let attempt = 0; attempt < 4; attempt++) {
            try {
                const fd = new FormData();
                fd.append('id',         isNoteTemp(work.id) ? 0 : work.id);
                fd.append('title',      work.title   || '');
                fd.append('content',    work.content || '');
                fd.append('version',    String(work.version != null && work.version !== '' ? work.version : 1));
                appendCsrfToken(fd);

                const res = await fetch('api/save_note.php', { method: 'POST', body: fd });

                let data;
                try {
                    data = await res.json();
                } catch (parseErr) {
                    console.error('Sync failed for note', work.id, parseErr);
                    unresolvedConflict = false;
                    await bumpOfflineRetry(work, 'Phản hồi máy chủ không hợp lệ');
                    break;
                }

                if (!res.ok) {
                    unresolvedConflict = false;
                    await bumpOfflineRetry(work, `Lỗi HTTP ${res.status}`);
                    break;
                }

                if (data.success) {
                    unresolvedConflict = false;
                    syncedCount++;
                    const delTx = db.transaction(['notes'], 'readwrite');
                    delTx.objectStore('notes').delete(work.id);
                    console.log(`[Offline] Synced ${work.id} → ${data.note_id}`);
                    break;
                }

                if (data.conflict) {
                    unresolvedConflict = true;
                    const serverVer = parseInt(data.version, 10);
                    work = {
                        ...work,
                        title:         work.title,
                        content:       work.content,
                        version:       Number.isFinite(serverVer) ? serverVer : (work.version || 1),
                        syncStatus:    'pending',
                        retryCount:    0,
                        nextRetryAt:   undefined,
                        lastSyncError: undefined,
                        updated_at:    work.updated_at
                    };
                    putOfflineNote(work);
                    continue;
                }

                unresolvedConflict = false;
                await bumpOfflineRetry(work, data.message || 'Đồng bộ thất bại');
                break;
            } catch (err) {
                console.error('Sync failed for note', work.id, err);
                unresolvedConflict = false;
                await bumpOfflineRetry(work, (err && err.message) || 'Lỗi mạng');
                break;
            }
        }

        if (unresolvedConflict) {
            conflictStillPending++;
            await bumpOfflineRetry(work, 'Xung đột phiên bản lặp lại; giữ bản cục bộ và chờ thử lại');
        }
    }

    offlineSyncIsRunning = false;
    emitOfflineSyncStateEvent();
    scheduleOfflineSyncRetrySweep();

    if (syncedCount > 0) {
        showToast(`Đã đồng bộ ${syncedCount} ghi chú từ chế độ offline`, 'success');
        setTimeout(liveSearch, 800);
    }
    if (conflictStillPending > 0) {
        showToast(
            conflictStillPending === 1
                ? 'Một ghi chú offline vẫn xung đột phiên bản sau vài lần thử; giữ bản cục bộ và sẽ thử lại sau.'
                : `${conflictStillPending} ghi chú offline vẫn xung đột phiên bản sau vài lần thử; giữ bản cục bộ và sẽ thử lại sau.`,
            'warning'
        );
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

Thư mục websocket
server.php
//code//
<?php
// websocket/server.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

use App\NoteWebSocket;

$wsCfg    = require __DIR__ . '/ws_secret.php';
$wsSecret = $wsCfg['secret'] ?? '';

if ($wsSecret === '') {
    fwrite(STDERR, "ERROR: websocket/ws_secret.php must define a non-empty secret.\n");
    exit(1);
}

// =====================================================
// WEBSOCKET SERVER
// =====================================================

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NoteWebSocket($pdo, $wsSecret)
        )
    ),
    8080
);

echo "🚀 WebSocket running at ws://localhost:8080\n";

$server->run();
//----//

ws_secret.php
//code//
<?php
/**
 * Shared secret for WebSocket HMAC tokens (must match NoteWebSocket + api/ws_token.php).
 * Override in production: set env NOTEAPP_WS_SECRET to a long random string.
 */
return [
    'secret' => getenv('NOTEAPP_WS_SECRET') ?: 'noteapp-ws-local-dev-secret-min-length-32-chars!',
];

//----//

activate.php
//code//
<?php
session_start();
require_once 'database.php';

$message = '';
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT id, display_name 
        FROM users 
        WHERE activation_token = ? 
          AND activation_expiry > NOW() 
          AND is_activated = 0 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("
            UPDATE users 
            SET is_activated = 1, 
                activation_token = NULL, 
                activation_expiry = NULL,
                email_verified_at = NOW()
            WHERE id = ?
        ");
        $update->execute([$user['id']]);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['is_activated'] = 1;

        session_regenerate_id(true);

        $message = "Kích hoạt tài khoản thành công!";
        header("Location: index.php?activated=1");
        exit;
    } else {
        $message = "Link kích hoạt không hợp lệ hoặc đã hết hạn!";
    }
} else {
    $message = "Token không hợp lệ!";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kích hoạt tài khoản</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h3>Kích hoạt tài khoản</h3>
                    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                    <a href="login.php" class="btn btn-primary">Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
//----//

composer.json
//code//
{
    "name": "noteapp/pro",
    "require": {
        "cboden/ratchet": "^0.4.4"
    },
    "autoload": {
        "psr-4": {
            "App\\": "App/"
        }
    }
}
//----//

composer.lock
//code//
{
    "_readme": [
        "This file locks the dependencies of your project to a known state",
        "Read more about it at https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies",
        "This file is @generated automatically"
    ],
    "content-hash": "5d52e477307bf204bf133801c3d7ba55",
    "packages": [
        {
            "name": "cboden/ratchet",
            "version": "v0.4.4",
            "source": {
                "type": "git",
                "url": "https://github.com/ratchetphp/Ratchet.git",
                "reference": "5012dc954541b40c5599d286fd40653f5716a38f"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ratchetphp/Ratchet/zipball/5012dc954541b40c5599d286fd40653f5716a38f",
                "reference": "5012dc954541b40c5599d286fd40653f5716a38f",
                "shasum": ""
            },
            "require": {
                "guzzlehttp/psr7": "^1.7|^2.0",
                "php": ">=5.4.2",
                "ratchet/rfc6455": "^0.3.1",
                "react/event-loop": ">=0.4",
                "react/socket": "^1.0 || ^0.8 || ^0.7 || ^0.6 || ^0.5",
                "symfony/http-foundation": "^2.6|^3.0|^4.0|^5.0|^6.0",
                "symfony/routing": "^2.6|^3.0|^4.0|^5.0|^6.0"
            },
            "require-dev": {
                "phpunit/phpunit": "~4.8"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Ratchet\\": "src/Ratchet"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "role": "Developer"
                },
                {
                    "name": "Matt Bonneau",
                    "role": "Developer"
                }
            ],
            "description": "PHP WebSocket library",
            "homepage": "http://socketo.me",
            "keywords": [
                "Ratchet",
                "WebSockets",
                "server",
                "sockets",
                "websocket"
            ],
            "support": {
                "chat": "https://gitter.im/reactphp/reactphp",
                "issues": "https://github.com/ratchetphp/Ratchet/issues",
                "source": "https://github.com/ratchetphp/Ratchet/tree/v0.4.4"
            },
            "time": "2021-12-14T00:20:41+00:00"
        },
        {
            "name": "evenement/evenement",
            "version": "v3.0.2",
            "source": {
                "type": "git",
                "url": "https://github.com/igorw/evenement.git",
                "reference": "0a16b0d71ab13284339abb99d9d2bd813640efbc"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/igorw/evenement/zipball/0a16b0d71ab13284339abb99d9d2bd813640efbc",
                "reference": "0a16b0d71ab13284339abb99d9d2bd813640efbc",
                "shasum": ""
            },
            "require": {
                "php": ">=7.0"
            },
            "require-dev": {
                "phpunit/phpunit": "^9 || ^6"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Evenement\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Igor Wiedler",
                    "email": "igor@wiedler.ch"
                }
            ],
            "description": "Événement is a very simple event dispatching library for PHP",
            "keywords": [
                "event-dispatcher",
                "event-emitter"
            ],
            "support": {
                "issues": "https://github.com/igorw/evenement/issues",
                "source": "https://github.com/igorw/evenement/tree/v3.0.2"
            },
            "time": "2023-08-08T05:53:35+00:00"
        },
        {
            "name": "guzzlehttp/psr7",
            "version": "2.9.0",
            "source": {
                "type": "git",
                "url": "https://github.com/guzzle/psr7.git",
                "reference": "7d0ed42f28e42d61352a7a79de682e5e67fec884"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/guzzle/psr7/zipball/7d0ed42f28e42d61352a7a79de682e5e67fec884",
                "reference": "7d0ed42f28e42d61352a7a79de682e5e67fec884",
                "shasum": ""
            },
            "require": {
                "php": "^7.2.5 || ^8.0",
                "psr/http-factory": "^1.0",
                "psr/http-message": "^1.1 || ^2.0",
                "ralouphie/getallheaders": "^3.0"
            },
            "provide": {
                "psr/http-factory-implementation": "1.0",
                "psr/http-message-implementation": "1.0"
            },
            "require-dev": {
                "bamarni/composer-bin-plugin": "^1.8.2",
                "http-interop/http-factory-tests": "0.9.0",
                "jshttp/mime-db": "1.54.0.1",
                "phpunit/phpunit": "^8.5.44 || ^9.6.25"
            },
            "suggest": {
                "laminas/laminas-httphandlerrunner": "Emit PSR-7 responses"
            },
            "type": "library",
            "extra": {
                "bamarni-bin": {
                    "bin-links": true,
                    "forward-command": false
                }
            },
            "autoload": {
                "psr-4": {
                    "GuzzleHttp\\Psr7\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Graham Campbell",
                    "email": "hello@gjcampbell.co.uk",
                    "homepage": "https://github.com/GrahamCampbell"
                },
                {
                    "name": "Michael Dowling",
                    "email": "mtdowling@gmail.com",
                    "homepage": "https://github.com/mtdowling"
                },
                {
                    "name": "George Mponos",
                    "email": "gmponos@gmail.com",
                    "homepage": "https://github.com/gmponos"
                },
                {
                    "name": "Tobias Nyholm",
                    "email": "tobias.nyholm@gmail.com",
                    "homepage": "https://github.com/Nyholm"
                },
                {
                    "name": "Márk Sági-Kazár",
                    "email": "mark.sagikazar@gmail.com",
                    "homepage": "https://github.com/sagikazarmark"
                },
                {
                    "name": "Tobias Schultze",
                    "email": "webmaster@tubo-world.de",
                    "homepage": "https://github.com/Tobion"
                },
                {
                    "name": "Márk Sági-Kazár",
                    "email": "mark.sagikazar@gmail.com",
                    "homepage": "https://sagikazarmark.hu"
                }
            ],
            "description": "PSR-7 message implementation that also provides common utility methods",
            "keywords": [
                "http",
                "message",
                "psr-7",
                "request",
                "response",
                "stream",
                "uri",
                "url"
            ],
            "support": {
                "issues": "https://github.com/guzzle/psr7/issues",
                "source": "https://github.com/guzzle/psr7/tree/2.9.0"
            },
            "funding": [
                {
                    "url": "https://github.com/GrahamCampbell",
                    "type": "github"
                },
                {
                    "url": "https://github.com/Nyholm",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/guzzlehttp/psr7",
                    "type": "tidelift"
                }
            ],
            "time": "2026-03-10T16:41:02+00:00"
        },
        {
            "name": "phpmailer/phpmailer",
            "version": "v7.0.2",
            "source": {
                "type": "git",
                "url": "https://github.com/PHPMailer/PHPMailer.git",
                "reference": "ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/PHPMailer/PHPMailer/zipball/ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088",
                "reference": "ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088",
                "shasum": ""
            },
            "require": {
                "ext-ctype": "*",
                "ext-filter": "*",
                "ext-hash": "*",
                "php": ">=5.5.0"
            },
            "require-dev": {
                "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
                "doctrine/annotations": "^1.2.6 || ^1.13.3",
                "php-parallel-lint/php-console-highlighter": "^1.0.0",
                "php-parallel-lint/php-parallel-lint": "^1.3.2",
                "phpcompatibility/php-compatibility": "^10.0.0@dev",
                "squizlabs/php_codesniffer": "^3.13.5",
                "yoast/phpunit-polyfills": "^1.0.4"
            },
            "suggest": {
                "decomplexity/SendOauth2": "Adapter for using XOAUTH2 authentication",
                "directorytree/imapengine": "For uploading sent messages via IMAP, see gmail example",
                "ext-imap": "Needed to support advanced email address parsing according to RFC822",
                "ext-mbstring": "Needed to send email in multibyte encoding charset or decode encoded addresses",
                "ext-openssl": "Needed for secure SMTP sending and DKIM signing",
                "greew/oauth2-azure-provider": "Needed for Microsoft Azure XOAUTH2 authentication",
                "hayageek/oauth2-yahoo": "Needed for Yahoo XOAUTH2 authentication",
                "league/oauth2-google": "Needed for Google XOAUTH2 authentication",
                "psr/log": "For optional PSR-3 debug logging",
                "symfony/polyfill-mbstring": "To support UTF-8 if the Mbstring PHP extension is not enabled (^1.2)",
                "thenetworg/oauth2-azure": "Needed for Microsoft XOAUTH2 authentication"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "PHPMailer\\PHPMailer\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "LGPL-2.1-only"
            ],
            "authors": [
                {
                    "name": "Marcus Bointon",
                    "email": "phpmailer@synchromedia.co.uk"
                },
                {
                    "name": "Jim Jagielski",
                    "email": "jimjag@gmail.com"
                },
                {
                    "name": "Andy Prevost",
                    "email": "codeworxtech@users.sourceforge.net"
                },
                {
                    "name": "Brent R. Matzelle"
                }
            ],
            "description": "PHPMailer is a full-featured email creation and transfer class for PHP",
            "support": {
                "issues": "https://github.com/PHPMailer/PHPMailer/issues",
                "source": "https://github.com/PHPMailer/PHPMailer/tree/v7.0.2"
            },
            "funding": [
                {
                    "url": "https://github.com/Synchro",
                    "type": "github"
                }
            ],
            "time": "2026-01-09T18:02:33+00:00"
        },
        {
            "name": "psr/http-factory",
            "version": "1.1.0",
            "source": {
                "type": "git",
                "url": "https://github.com/php-fig/http-factory.git",
                "reference": "2b4765fddfe3b508ac62f829e852b1501d3f6e8a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/php-fig/http-factory/zipball/2b4765fddfe3b508ac62f829e852b1501d3f6e8a",
                "reference": "2b4765fddfe3b508ac62f829e852b1501d3f6e8a",
                "shasum": ""
            },
            "require": {
                "php": ">=7.1",
                "psr/http-message": "^1.0 || ^2.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "1.0.x-dev"
                }
            },
            "autoload": {
                "psr-4": {
                    "Psr\\Http\\Message\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "PHP-FIG",
                    "homepage": "https://www.php-fig.org/"
                }
            ],
            "description": "PSR-17: Common interfaces for PSR-7 HTTP message factories",
            "keywords": [
                "factory",
                "http",
                "message",
                "psr",
                "psr-17",
                "psr-7",
                "request",
                "response"
            ],
            "support": {
                "source": "https://github.com/php-fig/http-factory"
            },
            "time": "2024-04-15T12:06:14+00:00"
        },
        {
            "name": "psr/http-message",
            "version": "2.0",
            "source": {
                "type": "git",
                "url": "https://github.com/php-fig/http-message.git",
                "reference": "402d35bcb92c70c026d1a6a9883f06b2ead23d71"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/php-fig/http-message/zipball/402d35bcb92c70c026d1a6a9883f06b2ead23d71",
                "reference": "402d35bcb92c70c026d1a6a9883f06b2ead23d71",
                "shasum": ""
            },
            "require": {
                "php": "^7.2 || ^8.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "2.0.x-dev"
                }
            },
            "autoload": {
                "psr-4": {
                    "Psr\\Http\\Message\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "PHP-FIG",
                    "homepage": "https://www.php-fig.org/"
                }
            ],
            "description": "Common interface for HTTP messages",
            "homepage": "https://github.com/php-fig/http-message",
            "keywords": [
                "http",
                "http-message",
                "psr",
                "psr-7",
                "request",
                "response"
            ],
            "support": {
                "source": "https://github.com/php-fig/http-message/tree/2.0"
            },
            "time": "2023-04-04T09:54:51+00:00"
        },
        {
            "name": "ralouphie/getallheaders",
            "version": "3.0.3",
            "source": {
                "type": "git",
                "url": "https://github.com/ralouphie/getallheaders.git",
                "reference": "120b605dfeb996808c31b6477290a714d356e822"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ralouphie/getallheaders/zipball/120b605dfeb996808c31b6477290a714d356e822",
                "reference": "120b605dfeb996808c31b6477290a714d356e822",
                "shasum": ""
            },
            "require": {
                "php": ">=5.6"
            },
            "require-dev": {
                "php-coveralls/php-coveralls": "^2.1",
                "phpunit/phpunit": "^5 || ^6.5"
            },
            "type": "library",
            "autoload": {
                "files": [
                    "src/getallheaders.php"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Ralph Khattar",
                    "email": "ralph.khattar@gmail.com"
                }
            ],
            "description": "A polyfill for getallheaders.",
            "support": {
                "issues": "https://github.com/ralouphie/getallheaders/issues",
                "source": "https://github.com/ralouphie/getallheaders/tree/develop"
            },
            "time": "2019-03-08T08:55:37+00:00"
        },
        {
            "name": "ratchet/rfc6455",
            "version": "v0.3.1",
            "source": {
                "type": "git",
                "url": "https://github.com/ratchetphp/RFC6455.git",
                "reference": "7c964514e93456a52a99a20fcfa0de242a43ccdb"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ratchetphp/RFC6455/zipball/7c964514e93456a52a99a20fcfa0de242a43ccdb",
                "reference": "7c964514e93456a52a99a20fcfa0de242a43ccdb",
                "shasum": ""
            },
            "require": {
                "guzzlehttp/psr7": "^2 || ^1.7",
                "php": ">=5.4.2"
            },
            "require-dev": {
                "phpunit/phpunit": "^5.7",
                "react/socket": "^1.3"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Ratchet\\RFC6455\\": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "role": "Developer"
                },
                {
                    "name": "Matt Bonneau",
                    "role": "Developer"
                }
            ],
            "description": "RFC6455 WebSocket protocol handler",
            "homepage": "http://socketo.me",
            "keywords": [
                "WebSockets",
                "rfc6455",
                "websocket"
            ],
            "support": {
                "chat": "https://gitter.im/reactphp/reactphp",
                "issues": "https://github.com/ratchetphp/RFC6455/issues",
                "source": "https://github.com/ratchetphp/RFC6455/tree/v0.3.1"
            },
            "time": "2021-12-09T23:20:49+00:00"
        },
        {
            "name": "react/cache",
            "version": "v1.2.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/cache.git",
                "reference": "d47c472b64aa5608225f47965a484b75c7817d5b"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/cache/zipball/d47c472b64aa5608225f47965a484b75c7817d5b",
                "reference": "d47c472b64aa5608225f47965a484b75c7817d5b",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0",
                "react/promise": "^3.0 || ^2.0 || ^1.1"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.5 || ^5.7 || ^4.8.35"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Cache\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async, Promise-based cache interface for ReactPHP",
            "keywords": [
                "cache",
                "caching",
                "promise",
                "reactphp"
            ],
            "support": {
                "issues": "https://github.com/reactphp/cache/issues",
                "source": "https://github.com/reactphp/cache/tree/v1.2.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2022-11-30T15:59:55+00:00"
        },
        {
            "name": "react/dns",
            "version": "v1.14.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/dns.git",
                "reference": "7562c05391f42701c1fccf189c8225fece1cd7c3"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/dns/zipball/7562c05391f42701c1fccf189c8225fece1cd7c3",
                "reference": "7562c05391f42701c1fccf189c8225fece1cd7c3",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0",
                "react/cache": "^1.0 || ^0.6 || ^0.5",
                "react/event-loop": "^1.2",
                "react/promise": "^3.2 || ^2.7 || ^1.2.1"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36",
                "react/async": "^4.3 || ^3 || ^2",
                "react/promise-timer": "^1.11"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Dns\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async DNS resolver for ReactPHP",
            "keywords": [
                "async",
                "dns",
                "dns-resolver",
                "reactphp"
            ],
            "support": {
                "issues": "https://github.com/reactphp/dns/issues",
                "source": "https://github.com/reactphp/dns/tree/v1.14.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-18T19:34:28+00:00"
        },
        {
            "name": "react/event-loop",
            "version": "v1.6.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/event-loop.git",
                "reference": "ba276bda6083df7e0050fd9b33f66ad7a4ac747a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/event-loop/zipball/ba276bda6083df7e0050fd9b33f66ad7a4ac747a",
                "reference": "ba276bda6083df7e0050fd9b33f66ad7a4ac747a",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36"
            },
            "suggest": {
                "ext-pcntl": "For signal handling support when using the StreamSelectLoop"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\EventLoop\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "ReactPHP's core reactor event loop that libraries can use for evented I/O.",
            "keywords": [
                "asynchronous",
                "event-loop"
            ],
            "support": {
                "issues": "https://github.com/reactphp/event-loop/issues",
                "source": "https://github.com/reactphp/event-loop/tree/v1.6.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-17T20:46:25+00:00"
        },
        {
            "name": "react/promise",
            "version": "v3.3.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/promise.git",
                "reference": "23444f53a813a3296c1368bb104793ce8d88f04a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/promise/zipball/23444f53a813a3296c1368bb104793ce8d88f04a",
                "reference": "23444f53a813a3296c1368bb104793ce8d88f04a",
                "shasum": ""
            },
            "require": {
                "php": ">=7.1.0"
            },
            "require-dev": {
                "phpstan/phpstan": "1.12.28 || 1.4.10",
                "phpunit/phpunit": "^9.6 || ^7.5"
            },
            "type": "library",
            "autoload": {
                "files": [
                    "src/functions_include.php"
                ],
                "psr-4": {
                    "React\\Promise\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "A lightweight implementation of CommonJS Promises/A for PHP",
            "keywords": [
                "promise",
                "promises"
            ],
            "support": {
                "issues": "https://github.com/reactphp/promise/issues",
                "source": "https://github.com/reactphp/promise/tree/v3.3.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-08-19T18:57:03+00:00"
        },
        {
            "name": "react/socket",
            "version": "v1.17.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket.git",
                "reference": "ef5b17b81f6f60504c539313f94f2d826c5faa08"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket/zipball/ef5b17b81f6f60504c539313f94f2d826c5faa08",
                "reference": "ef5b17b81f6f60504c539313f94f2d826c5faa08",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "^3.0 || ^2.0 || ^1.0",
                "php": ">=5.3.0",
                "react/dns": "^1.13",
                "react/event-loop": "^1.2",
                "react/promise": "^3.2 || ^2.6 || ^1.2.1",
                "react/stream": "^1.4"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36",
                "react/async": "^4.3 || ^3.3 || ^2",
                "react/promise-stream": "^1.4",
                "react/promise-timer": "^1.11"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Socket\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async, streaming plaintext TCP/IP and secure TLS socket server and client connections for ReactPHP",
            "keywords": [
                "Connection",
                "Socket",
                "async",
                "reactphp",
                "stream"
            ],
            "support": {
                "issues": "https://github.com/reactphp/socket/issues",
                "source": "https://github.com/reactphp/socket/tree/v1.17.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-19T20:47:34+00:00"
        },
        {
            "name": "react/stream",
            "version": "v1.4.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/stream.git",
                "reference": "1e5b0acb8fe55143b5b426817155190eb6f5b18d"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/stream/zipball/1e5b0acb8fe55143b5b426817155190eb6f5b18d",
                "reference": "1e5b0acb8fe55143b5b426817155190eb6f5b18d",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "^3.0 || ^2.0 || ^1.0",
                "php": ">=5.3.8",
                "react/event-loop": "^1.2"
            },
            "require-dev": {
                "clue/stream-filter": "~1.2",
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Stream\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Event-driven readable and writable streams for non-blocking I/O in ReactPHP",
            "keywords": [
                "event-driven",
                "io",
                "non-blocking",
                "pipe",
                "reactphp",
                "readable",
                "stream",
                "writable"
            ],
            "support": {
                "issues": "https://github.com/reactphp/stream/issues",
                "source": "https://github.com/reactphp/stream/tree/v1.4.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2024-06-11T12:45:25+00:00"
        },
        {
            "name": "symfony/deprecation-contracts",
            "version": "v3.7.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/deprecation-contracts.git",
                "reference": "50f59d1f3ca46d41ac911f97a78626b6756af35b"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/deprecation-contracts/zipball/50f59d1f3ca46d41ac911f97a78626b6756af35b",
                "reference": "50f59d1f3ca46d41ac911f97a78626b6756af35b",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/contracts",
                    "name": "symfony/contracts"
                },
                "branch-alias": {
                    "dev-main": "3.7-dev"
                }
            },
            "autoload": {
                "files": [
                    "function.php"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "A generic function and convention to trigger deprecation notices",
            "homepage": "https://symfony.com",
            "support": {
                "source": "https://github.com/symfony/deprecation-contracts/tree/v3.7.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-13T15:52:40+00:00"
        },
        {
            "name": "symfony/http-foundation",
            "version": "v6.4.35",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/http-foundation.git",
                "reference": "cffffd0a2c037117b742b4f8b379a22a2a33f6d2"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/http-foundation/zipball/cffffd0a2c037117b742b4f8b379a22a2a33f6d2",
                "reference": "cffffd0a2c037117b742b4f8b379a22a2a33f6d2",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1",
                "symfony/deprecation-contracts": "^2.5|^3",
                "symfony/polyfill-mbstring": "~1.1",
                "symfony/polyfill-php83": "^1.27"
            },
            "conflict": {
                "symfony/cache": "<6.4.12|>=7.0,<7.1.5"
            },
            "require-dev": {
                "doctrine/dbal": "^2.13.1|^3|^4",
                "predis/predis": "^1.1|^2.0",
                "symfony/cache": "^6.4.12|^7.1.5",
                "symfony/dependency-injection": "^5.4|^6.0|^7.0",
                "symfony/expression-language": "^5.4|^6.0|^7.0",
                "symfony/http-kernel": "^5.4.12|^6.0.12|^6.1.4|^7.0",
                "symfony/mime": "^5.4|^6.0|^7.0",
                "symfony/rate-limiter": "^5.4|^6.0|^7.0"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Symfony\\Component\\HttpFoundation\\": ""
                },
                "exclude-from-classmap": [
                    "/Tests/"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Fabien Potencier",
                    "email": "fabien@symfony.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Defines an object-oriented layer for the HTTP specification",
            "homepage": "https://symfony.com",
            "support": {
                "source": "https://github.com/symfony/http-foundation/tree/v6.4.35"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-03-06T11:15:58+00:00"
        },
        {
            "name": "symfony/polyfill-mbstring",
            "version": "v1.37.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/polyfill-mbstring.git",
                "reference": "6a21eb99c6973357967f6ce3708cd55a6bec6315"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/polyfill-mbstring/zipball/6a21eb99c6973357967f6ce3708cd55a6bec6315",
                "reference": "6a21eb99c6973357967f6ce3708cd55a6bec6315",
                "shasum": ""
            },
            "require": {
                "ext-iconv": "*",
                "php": ">=7.2"
            },
            "provide": {
                "ext-mbstring": "*"
            },
            "suggest": {
                "ext-mbstring": "For best performance"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/polyfill",
                    "name": "symfony/polyfill"
                }
            },
            "autoload": {
                "files": [
                    "bootstrap.php"
                ],
                "psr-4": {
                    "Symfony\\Polyfill\\Mbstring\\": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Symfony polyfill for the Mbstring extension",
            "homepage": "https://symfony.com",
            "keywords": [
                "compatibility",
                "mbstring",
                "polyfill",
                "portable",
                "shim"
            ],
            "support": {
                "source": "https://github.com/symfony/polyfill-mbstring/tree/v1.37.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-10T17:25:58+00:00"
        },
        {
            "name": "symfony/polyfill-php83",
            "version": "v1.37.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/polyfill-php83.git",
                "reference": "3600c2cb22399e25bb226e4a135ce91eeb2a6149"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/polyfill-php83/zipball/3600c2cb22399e25bb226e4a135ce91eeb2a6149",
                "reference": "3600c2cb22399e25bb226e4a135ce91eeb2a6149",
                "shasum": ""
            },
            "require": {
                "php": ">=7.2"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/polyfill",
                    "name": "symfony/polyfill"
                }
            },
            "autoload": {
                "files": [
                    "bootstrap.php"
                ],
                "psr-4": {
                    "Symfony\\Polyfill\\Php83\\": ""
                },
                "classmap": [
                    "Resources/stubs"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Symfony polyfill backporting some PHP 8.3+ features to lower PHP versions",
            "homepage": "https://symfony.com",
            "keywords": [
                "compatibility",
                "polyfill",
                "portable",
                "shim"
            ],
            "support": {
                "source": "https://github.com/symfony/polyfill-php83/tree/v1.37.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-10T17:25:58+00:00"
        },
        {
            "name": "symfony/routing",
            "version": "v6.4.37",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/routing.git",
                "reference": "48035d186798d27d375d95aad37db8fe097e4048"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/routing/zipball/48035d186798d27d375d95aad37db8fe097e4048",
                "reference": "48035d186798d27d375d95aad37db8fe097e4048",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1",
                "symfony/deprecation-contracts": "^2.5|^3"
            },
            "conflict": {
                "doctrine/annotations": "<1.12",
                "symfony/config": "<6.2",
                "symfony/dependency-injection": "<5.4",
                "symfony/yaml": "<5.4"
            },
            "require-dev": {
                "doctrine/annotations": "^1.12|^2",
                "psr/log": "^1|^2|^3",
                "symfony/config": "^6.2|^7.0",
                "symfony/dependency-injection": "^5.4|^6.0|^7.0",
                "symfony/expression-language": "^5.4|^6.0|^7.0",
                "symfony/http-foundation": "^5.4|^6.0|^7.0",
                "symfony/yaml": "^5.4|^6.0|^7.0"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Symfony\\Component\\Routing\\": ""
                },
                "exclude-from-classmap": [
                    "/Tests/"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Fabien Potencier",
                    "email": "fabien@symfony.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Maps an HTTP request to a set of configuration variables",
            "homepage": "https://symfony.com",
            "keywords": [
                "router",
                "routing",
                "uri",
                "url"
            ],
            "support": {
                "source": "https://github.com/symfony/routing/tree/v6.4.37"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-18T13:45:55+00:00"
        }
    ],
    "packages-dev": [],
    "aliases": [],
    "minimum-stability": "stable",
    "stability-flags": {},
    "prefer-stable": false,
    "prefer-lowest": false,
    "platform": {},
    "platform-dev": {},
    "plugin-api-version": "2.9.0"
}

//----//

config.php
//code//
<?php
// Đảm bảo không sử dụng hardcoded ports hay hostnames trong source code 
define('BASE_URL', '/'); // Vì project đặt trực tiếp trong htdocs theo yêu cầu [cite: 118, 119]

// Cấu hình Mail (Dành cho Phase 2: Kích hoạt & Reset password) [cite: 21, 25]
// Bạn sẽ cần thư viện PHPMailer hoặc tương tự ở Phase sau.
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
?>
//----//
database.php
//code//
<?php
// Cấu hình Database
$host = 'localhost';
$db   = 'note_management';
$user = 'root'; // Thay đổi theo máy của bạn (thường là root trên XAMPP)
$pass = '';     // Thay đổi theo máy của bạn (thường để trống trên XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Lưu ý: Trong môi trường production không nên hiển thị lỗi chi tiết thế này
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&family=Open+Sans:wght@300;400;500;600;700&family=Lato:wght@300;400;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-size: <?= htmlspecialchars($user_font_size) ?>; 
        }
        
        :root {
            --note-default-color: <?= htmlspecialchars($user_note_color) ?>;
            --note-text-color: <?= htmlspecialchars($_SESSION['text_color'] ?? '#0A1024') ?>;
        }
        
        .note-card {
            background-color: var(--note-default-color) !important;
        }
        .note-card .card-title,
        .note-card .card-text {
            color: var(--note-text-color) !important;
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
                onclick="showProfileModal()" 
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
        <button id="toggleBulkModeBtn" class="btn btn-outline-secondary shadow-sm px-3" title="Chọn nhiều ghi chú">
            <i class="bi bi-check2-square"></i> Chọn
        </button>
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;"></h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
    <!-- Bulk action toolbar (ẩn mặc định) -->
<div id="bulkToolbar" class="fixed-bottom mb-3 d-none justify-content-center">
    <div class="bg-body-tertiary rounded-pill shadow-lg p-2 d-flex gap-2">
        <button id="bulkDeleteBtn" class="btn btn-sm btn-danger rounded-pill"><i class="bi bi-trash3"></i> Xóa</button>
        <button id="bulkRestoreBtn" class="btn btn-sm btn-success rounded-pill d-none"><i class="bi bi-arrow-counterclockwise"></i> Khôi phục</button>
        <button id="bulkPermanentBtn" class="btn btn-sm btn-dark rounded-pill d-none"><i class="bi bi-x-octagon"></i> Xóa vĩnh viễn</button>
        <button id="bulkShareBtn" class="btn btn-sm btn-info rounded-pill"><i class="bi bi-share"></i> Chia sẻ</button>
        <button id="bulkCancelBtn" class="btn btn-sm btn-secondary rounded-pill"><i class="bi bi-x-lg"></i> Hủy</button>
        <span id="bulkCounter" class="align-self-center ms-2 small text-muted">0</span>
    </div>
</div>
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
    email:       "<?= addslashes($_SESSION['email'] ?? '') ?>",
    displayName: "<?= addslashes($_SESSION['display_name'] ?? '') ?>",
    avatar:      "<?= htmlspecialchars($user_avatar) ?>",
    fontSize:    "<?= htmlspecialchars($user_font_size) ?>",
    theme:       "<?= htmlspecialchars($user_theme) ?>",
    noteColor:   "<?= htmlspecialchars($user_note_color) ?>",
    textColor:   "<?= htmlspecialchars($_SESSION['text_color'] ?? '#0A1024') ?>",
    fontFamily:  "<?= htmlspecialchars($_SESSION['font_family'] ?? 'Inter, system-ui, sans-serif') ?>",
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
                    $_SESSION['email']        = $user['email'];
                    $_SESSION['text_color']   = $user['text_color'] ?? '#0A1024';
                    $_SESSION['font_family']  = $user['font_family'] ?? 'Inter, system-ui, sans-serif';
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

<!-- Modal Profile MỚI (thay thế hoàn toàn) -->
<div class="modal fade" id="profileModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg" id="profileModalContent">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="profileModalTitle">
                    <i class="bi bi-person-circle"></i> Tài khoản
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="profileModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
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

<!-- Modal Bulk Share -->
<div class="modal fade" id="bulkShareModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-share-fill"></i> Chia sẻ nhiều ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Email người nhận (cách nhau bằng dấu phẩy)</label>
                    <input type="text" id="bulkShareEmails" class="form-control" placeholder="user1@example.com, user2@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Quyền truy cập</label>
                    <select id="bulkSharePermission" class="form-select">
                        <option value="read">Chỉ xem</option>
                        <option value="edit">Cho phép chỉnh sửa</option>
                    </select>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i> Ghi chú sẽ được chia sẻ đến tất cả email trên, với quyền được chọn.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="bulkShareConfirmBtn">Xác nhận chia sẻ</button>
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
    $sessTok = $_SESSION['csrf_token'] ?? null;
    $postTok = $_POST['csrf_token'] ?? null;
    if (!is_string($sessTok) || $sessTok === '' || !is_string($postTok) || !hash_equals($sessTok, $postTok)) {
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

                $expiryOk = $user
                    && !empty($user['reset_token_expiry'])
                    && $user['reset_token_expiry'] > date('Y-m-d H:i:s');
                $stored   = isset($user['reset_token']) ? (string) $user['reset_token'] : '';
                $tokenOk  = $user && $stored !== '' && hash_equals($stored, (string) $input);

                $is_valid = $token_verified || ($expiryOk && $tokenOk);

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

service-worker.js
// ====================== SERVICE WORKER - NOTEAPP PRO v1.8 ======================
// Cải tiến: Background Sync + Offline Queue Support

const CACHE_NAME = 'noteapp-v1.8';
const OFFLINE_QUEUE = 'offline-queue';

// ====================== INSTALL ======================
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker v1.8...');
    self.skipWaiting();
});

// ====================== ACTIVATE ======================
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker v1.8...');

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

    // ==================== API & PHP - NETWORK FIRST (với fallback) ====================
    if (url.pathname.startsWith('/api/') || url.pathname.endsWith('.php')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    return response;
                })
                .catch(() => {
                    console.warn('[SW] API Fetch Failed (Offline)');
                    return new Response(
                        JSON.stringify({
                            success: false,
                            message: 'Bạn đang offline. Thay đổi sẽ được đồng bộ khi có mạng.',
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
                        .catch(() => cachedResponse);

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
        event.waitUntil(syncOfflineQueue());
    }
});

async function syncOfflineQueue() {
    try {
        const db = await openIDB();
        const queue = await getAllFromStore(db, 'queue');

        console.log(`[SW] Found ${queue.length} items in offline queue`);

        for (const item of queue) {
            try {
                const response = await fetch(item.url, {
                    method: item.method || 'POST',
                    headers: item.headers || {},
                    body: item.body
                });

                if (response.ok) {
                    await deleteFromStore(db, 'queue', item.id);
                    console.log(`[SW] Synced item ${item.id} successfully`);
                } else {
                    console.warn(`[SW] Sync failed for item ${item.id}:`, response.status);
                }
            } catch (err) {
                console.error(`[SW] Sync error for item ${item.id}:`, err);
            }
        }
    } catch (e) {
        console.error('[SW] Background sync failed:', e);
    }
}

// ====================== INDEXEDDB HELPERS ======================
function openIDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('NoteAppDB', 1);

        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('queue')) {
                const store = db.createObjectStore('queue', { autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function getAllFromStore(db, storeName) {
    return new Promise((resolve) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result);
    });
}

async function deleteFromStore(db, storeName, key) {
    return new Promise((resolve) => {
        const tx = db.transaction(storeName, 'readwrite');
        tx.objectStore(storeName).delete(key);
        tx.oncomplete = () => resolve();
    });
}

// ====================== PUSH NOTIFICATION (tương lai) ======================
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');
});

console.log('[SW] NoteApp Service Worker v1.8 loaded successfully!');
//----//