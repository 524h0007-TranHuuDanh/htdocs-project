Cấu trúc thư mục:
HTDOCS - api - auth_helper.php
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
               //
                |
                - change_color.php
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
                //
                |
                - delete_image.php
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
                //
                |
                - delete_note.php
                //code//
                <?php
                require_once 'auth_helper.php';
                require_once '../database.php';
                check_login();

                $id = $_POST['id'] ?? 0;
                $action = $_POST['action'] ?? 'trash'; // 'trash' hoặc 'permanent'

                try {
                    if ($action === 'trash') {
                        // Chuyển vào thùng rác
                        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
                    } else {
                        // Xóa vĩnh viễn (bao gồm cả ảnh, nhãn liên quan thông qua CASCADE nếu db đã set, hoặc xóa cứng)
                        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
                    }
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                ?>
                //
                |
                - get_note_images.php
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
                //
                |
                - get_note_labels.php
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
                //
                |
                - get_shares.php
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
                //
                |
                - lock_note.php
                //code//
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
                //
                |
                - manage_labels.php
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
                //
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
                //
                |
                - revoke_share.php
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
                //
                |
                - save_note.php
                //code//
                <?php
                // api/save_note.php
                // SỬA CRIT 3: Dùng auth_helper thay vì session_start() + kiểm tra thủ công
                require_once 'auth_helper.php';
                require_once '../database.php';
                check_login();

                header('Content-Type: application/json');

                $user_id = $_SESSION['user_id'];
                $id      = trim($_POST['id']      ?? '');
                $title   = $_POST['title']        ?? '';
                $content = $_POST['content']      ?? '';

                try {
                    if (empty($id)) {
                        // Tạo ghi chú mới
                        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $title, $content]);
                        echo json_encode(['success' => true, 'note_id' => $pdo->lastInsertId()]);
                    } else {
                        $id = intval($id);

                        // Kiểm tra quyền: chủ sở hữu HOẶC được share với quyền edit
                        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                        $meStmt->execute([$user_id]);
                        $my_email = $meStmt->fetchColumn();

                        $stmt = $pdo->prepare("
                            SELECT n.user_id,
                                (SELECT permission FROM shared_notes
                                    WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
                            FROM notes n
                            WHERE n.id = ?
                        ");
                        $stmt->execute([$my_email, $id]);
                        $note = $stmt->fetch();

                        if ($note && ($note['user_id'] == $user_id || $note['permission'] === 'edit')) {
                            $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$title, $content, $id]);
                            echo json_encode(['success' => true, 'note_id' => $id]);
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Bạn không có quyền chỉnh sửa ghi chú này!']);
                        }
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                //
                |
                - search.php
                //code//
           <?php
// api/search.php

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

    // ─────────────────────────────────────────────
    // SHARED NOTES
    // ─────────────────────────────────────────────
    if ($view_mode === 'shared') {

        $meStmt = $pdo->prepare("
            SELECT email
            FROM users
            WHERE id = ?
        ");

        $meStmt->execute([$user_id]);

        $my_email = $meStmt->fetchColumn();

        $sql = "
            SELECT
                n.*,
                sn.permission,
                u.display_name AS owner_name,
                sn.shared_at
            FROM shared_notes sn
            JOIN notes n
                ON sn.note_id = n.id
            JOIN users u
                ON n.user_id = u.id
            WHERE sn.recipient_email = ?
              AND n.user_id != ?
              AND n.is_trashed = 0
        ";

        $params[] = $my_email;
        $params[] = $user_id;

    }

    // ─────────────────────────────────────────────
    // TRASH
    // ─────────────────────────────────────────────
    elseif ($view_mode === 'trash') {

        $sql = "
            SELECT n.*
            FROM notes n
            WHERE n.user_id = ?
              AND n.is_trashed = 1
        ";

        $params[] = $user_id;

    }

    // ─────────────────────────────────────────────
    // MY NOTES
    // ─────────────────────────────────────────────
    else {

        $sql = "
            SELECT n.*
            FROM notes n
            WHERE n.user_id = ?
              AND n.is_trashed = 0
        ";

        $params[] = $user_id;

        if ($label_id && $label_id !== 'null') {

            $sql .= "
                AND n.id IN (
                    SELECT note_id
                    FROM note_labels
                    WHERE label_id = ?
                )
            ";

            $params[] = intval($label_id);
        }
    }

    // ─────────────────────────────────────────────
    // SEARCH
    // ─────────────────────────────────────────────
    $sql .= "
        AND (
            n.title LIKE ?
            OR n.content LIKE ?
        )
    ";

    $params[] = $searchTerm;
    $params[] = $searchTerm;

    // ─────────────────────────────────────────────
    // ORDER
    // ─────────────────────────────────────────────
    if ($view_mode === 'shared') {

        $sql .= "
            ORDER BY
                sn.shared_at DESC,
                n.updated_at DESC
        ";

    } else {

        $sql .= "
            ORDER BY
                n.is_pinned DESC,
                n.pinned_at DESC,
                n.updated_at DESC
        ";
    }

    // ─────────────────────────────────────────────
    // EXECUTE
    // ─────────────────────────────────────────────
    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────
    // HIDE LOCKED CONTENT
    // ─────────────────────────────────────────────
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
                //
                |
                - update_profile.php
                //code//
            <?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

// =========================
// CHECK LOGIN
// =========================
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

// =========================
// DEFAULT AVATAR
// =========================
$default_avatar = 'uploads/avatars/default-avatar.png';

// =========================
// INPUT
// =========================
$font_size   = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$note_color  = $_POST['note_color'] ?? '#ffffff';

// =========================
// VALIDATE
// =========================
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

try {

    // =========================
    // CURRENT AVATAR
    // =========================
    $avatar_path = !empty($_SESSION['avatar'])
        ? $_SESSION['avatar']
        : $default_avatar;

    // =========================
    // UPLOAD NEW AVATAR
    // =========================
    if (
        isset($_FILES['avatar']) &&
        $_FILES['avatar']['error'] === UPLOAD_ERR_OK
    ) {

        $upload_dir = __DIR__ . '/../uploads/avatars/';

        // Tạo folder nếu chưa có
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Extension
        $ext = strtolower(
            pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION)
        );

        // Cho phép
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed_ext)) {
            throw new Exception('File ảnh không hợp lệ');
        }

        // Tên file mới
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;

        $target = $upload_dir . $new_name;

        // Upload
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
            throw new Exception('Upload avatar thất bại');
        }

        // =========================
        // DELETE OLD AVATAR
        // =========================
        if (
            !empty($_SESSION['avatar']) &&
            $_SESSION['avatar'] !== $default_avatar
        ) {

            $old_file = __DIR__ . '/../' . $_SESSION['avatar'];

            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Avatar mới
        $avatar_path = 'uploads/avatars/' . $new_name;
    }

    // =========================
    // UPDATE DB
    // =========================
    $stmt = $pdo->prepare("
        UPDATE users
        SET
            font_size = ?,
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

    // =========================
    // UPDATE SESSION
    // =========================
    $_SESSION['font_size']   = $font_size;
    $_SESSION['theme_color'] = $theme_color;
    $_SESSION['note_color']  = $note_color;
    $_SESSION['avatar']      = $avatar_path;

    // =========================
    // SUCCESS
    // =========================
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thành công',
        'avatar' => $avatar_path
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
                //
                |
                - upload_image.php
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
                //
                |
                - verify_note.php
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
                //
       | 
       - assets - css - style.css
                |
                - js - app.js
       |
       - uploads
       |
       -vendor...
       |
       activate.php
       //code//
       <?php
session_start();
require_once 'database.php';

$message = '';
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE activation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_activated = 1, activation_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        // Tự động đăng nhập sau khi kích hoạt
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['is_activated'] = 1;

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
        //
        |
        - composer.lock
        //code//
        {
            "_readme": [
                "This file locks the dependencies of your project to a known state",
                "Read more about it at https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies",
                "This file is @generated automatically"
            ],
            "content-hash": "826f515f5ef16946d3e3ee3e3205b25e",
            "packages": [
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
        //
        |
        - config.php
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
        //
        |
        - database.php
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
        //
        |
        - database.sql
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
        //
        |
        - index.php
        //code//
 <?php
require_once 'api/auth_helper.php';

check_login();

// Avatar mặc định
$default_avatar = 'uploads/avatars/default-avatar.png';

$user_font_size  = $_SESSION['font_size']   ?? '16px';
$user_theme      = $_SESSION['theme_color'] ?? 'light';
$user_note_color = $_SESSION['note_color']  ?? '#ffffff';

// Nếu chưa có avatar thì dùng avatar mặc định
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { font-size: <?= htmlspecialchars($user_font_size) ?> !important; transition: background-color 0.3s, color 0.3s; }
        .note-card { cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid rgba(0,0,0,0.125); }
        .note-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .note-grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .note-list-view { display: flex; flex-direction: column; gap: 15px; }
        .cp { cursor: pointer; }
        textarea { resize: none; }
        .color-btn { width: 25px; height: 25px; border-radius: 50%; border: 1px solid #ccc; display: inline-block; cursor: pointer; margin-right: 5px; }
        .color-btn:hover { transform: scale(1.1); }
        .nav-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            object-position: center;
            border: 2px solid white;
            cursor: pointer;
            transition: transform 0.2s;
            overflow: hidden;
            display: block;
            background: #fff;
        }        
        .nav-avatar:hover { transform: scale(1.1); }
    </style>
</head>
<body class="bg-body text-body">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">📝 NoteApp</a>
        <form class="d-flex mx-auto w-50" onsubmit="return false;">
            <input class="form-control me-2" type="search" id="searchInput" placeholder="Tìm kiếm ghi chú..." oninput="liveSearch()">
        </form>
        <div class="d-flex align-items-center gap-3 text-white">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
<img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" 
     class="nav-avatar"
     onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()"
     title="Cài đặt tài khoản">
            <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
        </div>
    </div>
</nav>

<!-- PROMINENT NOTIFICATION -->
<?php if (isset($_SESSION['is_activated']) && $_SESSION['is_activated'] == 0): ?>
<div class="alert alert-warning alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Tài khoản chưa được xác minh!</strong> 
    Vui lòng kiểm tra email và click vào link kích hoạt để hoàn tất đăng ký.
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
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;">...</h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
</div>

<!-- Modal ghi chú -->
<div class="modal fade" id="noteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg" id="modalContentWrapper">
            <div class="modal-header border-0 pb-0">
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent"
                       placeholder="Tiêu đề..." oninput="autoSave()">
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10"
                          placeholder="Bạn đang nghĩ gì?..." oninput="autoSave()"></textarea>
                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mb-3"></div>

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

               <div class="p-3 bg-body-secondary rounded border mb-3" id="shareManagerSection" style="display:none;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus"></i> Chia sẻ ghi chú này</h6>
                    
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="share_input" class="form-control"
                            placeholder="Nhập email (cách nhau bởi dấu phẩy)...">
                        <select id="sharePermission" class="form-select" style="max-width: 140px;">
                            <option value="read">Chỉ xem</option>
                            <option value="edit">Cho phép sửa</option>
                        </select>
                        <button class="btn btn-success" onclick="shareNote()">Chia sẻ</button>
                    </div>
                    <small class="text-muted">Ví dụ: user1@gmail.com, user2@gmail.com</small>
                    
                    <ul id="sharedUsersList" class="list-group list-group-flush small mt-3"></ul>
                </div>

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

<!-- Modal Profile - ĐÃ HOÀN THIỆN -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>"
                     class="rounded-circle mb-3 border" style="
                                width:120px;
                                height:120px;
                                object-fit:cover;
                                object-position:center;
                                border-radius:50%;
                                overflow:hidden;
                                background:white;
                                ">
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
                            <option value="14px" <?= $user_font_size=='14px'?'selected':'' ?>>Nhỏ</option>
                            <option value="16px" <?= $user_font_size=='16px'?'selected':'' ?>>Vừa</option>
                            <option value="18px" <?= $user_font_size=='18px'?'selected':'' ?>>Lớn</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Giao diện</label>
                        <select id="settingTheme" class="form-select">
                            <option value="light" <?= $user_theme=='light'?'selected':'' ?>>Sáng</option>
                            <option value="dark"  <?= $user_theme=='dark'?'selected':'' ?>>Tối</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Màu ghi chú</label>
                        <input type="color" id="settingNoteColor" class="form-control form-control-color w-100" 
                               value="<?= htmlspecialchars($user_note_color) ?>">
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
<!-- ==================== MODAL NHẬP MẬT KHẨU ==================== -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle">🔒 Nhập mật khẩu ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="password" id="notePasswordInput" class="form-control" 
                       placeholder="Nhập mật khẩu..." autocomplete="current-password">
                <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="submitNotePassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
let typingTimer, searchTimer;
let currentLabelId     = null;
let currentViewMode    = 'my_notes';
let currentPermission  = 'owner';
let isLockedState      = false;
let currentNoteId      = null;
let passwordModal      = null;
let tempOpenData       = null;

document.addEventListener('DOMContentLoaded', () => {
    passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));
    setViewMode('my_notes');
});


// ── VIEW MODE ─────────────────────────────────────────────────
function setViewMode(mode) {
    currentViewMode = mode;
    currentLabelId  = null;

    document.getElementById('btnViewShared').style.display  = mode === 'shared'   ? 'none' : 'block';
    document.getElementById('btnViewTrash').style.display   = mode === 'trash'    ? 'none' : 'block';
    document.getElementById('btnViewMyNotes').style.display = mode === 'my_notes' ? 'none' : 'block';

    const viewTitle    = document.getElementById('viewTitle');
    const btnCreate    = document.getElementById('btnCreateNote');
    const addLabelGroup = document.getElementById('addLabelGroup');

    if (mode === 'my_notes') {
        viewTitle.style.display = 'none';
        btnCreate.style.display = 'block';
        addLabelGroup.style.display = 'flex';
    } else if (mode === 'trash') {
        viewTitle.innerHTML  = '🗑️ THÙNG RÁC';
        viewTitle.style.display = 'block';
        viewTitle.className  = 'text-danger fw-bold m-0 align-self-center';
        btnCreate.style.display = 'none';
        addLabelGroup.style.display = 'none';
    } else if (mode === 'shared') {
        viewTitle.innerHTML  = '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';
        viewTitle.style.display = 'block';
        viewTitle.className  = 'text-info fw-bold m-0 align-self-center';
        btnCreate.style.display = 'none';
        addLabelGroup.style.display = 'none';
    }

    // SỬA WARN: Gọi loadFilterLabels với callback để tránh race condition
    loadFilterLabels(() => liveSearch());
    startAutoRefresh();
}

// ── SEARCH ────────────────────────────────────────────────────
function liveSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        let url = `api/search.php?q=${encodeURIComponent(document.getElementById('searchInput').value)}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;
        fetch(url).then(res => res.json()).then(renderNotes).catch(() => renderNotes([]));
    }, 300);
}

// ── SEARCH & RENDER NOTES ─────────────────────────────────────
function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = {
            trash: 'Thùng rác trống.',
            shared: 'Chưa có ghi chú nào được chia sẻ với bạn.',
            my_notes: 'Chưa có ghi chú nào. Hãy tạo mới!'
        };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass   = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor    = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName  = n.owner_name  || '';
        const permission = n.permission  || 'owner';

        // ==================== ICON NHẬN DIỆN ====================
        let icons = '';
        
        // Icon Khóa
        if (n.is_locked == 1) {
            icons += '<i class="bi bi-lock-fill text-warning me-1" title="Ghi chú đã khóa"></i>';
        }
        
        // Icon Được chia sẻ
        if (ownerName) {
            icons += '<i class="bi bi-people-fill text-info me-1" title="Được chia sẻ"></i>';
        }
        
        // Icon Ghim
        if (n.is_pinned == 1) {
            icons += '<i class="bi bi-pin-fill text-danger me-1" title="Đã ghim"></i>';
        }

        // ==================== THÔNG TIN CHIA SẺ ====================
        let shareInfo = '';
        if (ownerName) {
            const permBadge = permission === 'edit' 
                ? `<span class="badge bg-success ms-1">✏️ Edit</span>` 
                : `<span class="badge bg-secondary ms-1">👁️ View</span>`;
            
            shareInfo = `
                <div class="position-absolute bottom-0 start-0 end-0 px-3 pb-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="bi bi-person"></i> ${escapeHtml(ownerName)}
                    </small>
                    ${permBadge}
                </div>`;
        }

        // Tạo card
        const card = document.createElement('div');
        card.className = 'card note-card h-100';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className = 'card-body position-relative pb-4';
        
        body.dataset.id         = n.id;
        body.dataset.title      = n.title    || '';
        body.dataset.content    = n.content  || '';
        body.dataset.isLocked   = n.is_locked || 0;
        body.dataset.color      = n.color    || '';
        body.dataset.permission = permission;
        body.dataset.ownerName  = ownerName;

        body.addEventListener('click', function () {
            handleNoteOpen(
                parseInt(this.dataset.id),
                this.dataset.title,
                this.dataset.content,
                parseInt(this.dataset.isLocked),
                this.dataset.color,
                this.dataset.permission,
                this.dataset.ownerName
            );
        });

        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0"
                       onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})">
                       <i class="bi ${pinClass} fs-5"></i>
                   </button>`
                : ''}
            
            <h5 class="card-title text-truncate d-flex align-items-center gap-1">
                ${icons}
                ${escapeHtml(n.title) || 'Không tiêu đề'}
            </h5>
            
            <p class="card-text text-muted text-truncate" style="white-space:pre-wrap; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                ${escapeHtml(n.content) || 'Không có nội dung...'}
            </p>
            
            ${shareInfo}
        `;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ── MỞ GHI CHÚ ───────────────────────────────────────────────
// ── MỞ GHI CHÚ ───────────────────────────────────────────────
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    currentNoteId = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        document.getElementById('passwordModalTitle').textContent = '🔒 Ghi chú đã bị khóa';
        document.getElementById('notePasswordInput').value = '';
        document.getElementById('passwordError').style.display = 'none';
        passwordModal.show();
        setTimeout(() => document.getElementById('notePasswordInput').focus(), 500);
        
        window.tempOpenData = {id, title, content, color, permission, ownerName};
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    const password = document.getElementById('notePasswordInput').value.trim();
    const errorEl = document.getElementById('passwordError');

    if (!password) {
        errorEl.textContent = "Vui lòng nhập mật khẩu!";
        errorEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append('note_id', currentNoteId);
    fd.append('password', password);

    fetch('api/verify_note.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            passwordModal.hide();
            isLockedState = true;
            openNoteModal(
                window.tempOpenData.id,
                d.title,
                d.content,
                d.color,
                d.permission,
                window.tempOpenData.ownerName
            );
        } else {
            errorEl.textContent = d.message || "Mật khẩu không đúng!";
            errorEl.style.display = 'block';
            document.getElementById('notePasswordInput').value = '';
            document.getElementById('notePasswordInput').focus();
        }
    })
    .catch(() => {
        errorEl.textContent = "Lỗi kết nối!";
        errorEl.style.display = 'block';
    });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    document.getElementById('noteId').value            = id;
    document.getElementById('noteTitle').value         = title;
    document.getElementById('noteContent').value       = content;
    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText    = '';
    document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';

    const isTrash  = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice   = document.getElementById('sharedNotice');

    // Reset tất cả sections
    ['toolsSection', 'colorSection', 'shareManagerSection',
     'btnTrashNote', 'btnRestoreNote', 'btnDeletePermanent'].forEach(el => {
        document.getElementById(el).style.display = 'none';
    });

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display    = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
    } else if (isShared) {
        notice.style.display = 'block';
        
        // Lấy thời gian chia sẻ (nếu có)
        const sharedTime = n && n.shared_at 
            ? new Date(n.shared_at).toLocaleString('vi-VN', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit', 
                hour: '2-digit', 
                minute: '2-digit' 
            }) 
            : 'Không xác định';

        notice.innerHTML = `
            <strong>Được chia sẻ bởi:</strong> <b>${escapeHtml(ownerName)}</b><br>
            <strong>Quyền:</strong> <b>${permission === 'edit' ? '✅ Có thể chỉnh sửa' : '👁️ Chỉ xem'}</b><br>
            <strong>Chia sẻ lúc:</strong> <small class="text-muted">${sharedTime}</small>
        `;
        document.getElementById('noteTitle').readOnly   = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';
        if (id) {
        fetch(`api/get_note_images.php?note_id=${id}`)
            .then(res => res.json())
            .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
        }
    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = false;
        document.getElementById('noteContent').readOnly = false;
        if (id) {
            document.getElementById('toolsSection').style.display     = 'block';
            document.getElementById('colorSection').style.display     = 'block';
            document.getElementById('shareManagerSection').style.display = 'block';
            document.getElementById('btnTrashNote').style.display     = 'block';
            document.getElementById('btnLock').innerHTML = isLockedState
                ? '<i class="bi bi-unlock"></i> Mở khóa'
                : '<i class="bi bi-lock"></i> Đặt mật khẩu';
            fetch(`api/get_note_images.php?note_id=${id}`)
                .then(res => res.json())
                .then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
        }
    }
    if (isShared && permission === 'edit') {
        startAutoRefresh();
    }
    noteModal.show();
}

// ── AUTO SAVE ─────────────────────────────────────────────────
function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    document.getElementById('saveStatus').innerText = 'Đang lưu...';
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        const id = document.getElementById('noteId').value;
        const t  = document.getElementById('noteTitle').value;
        const c  = document.getElementById('noteContent').value;
        if (!t.trim() && !c.trim()) return;
        fetch('api/save_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(t)}&content=${encodeURIComponent(c)}`
        })
        .then(res => res.json())
        .then(d => {
            if (d.success && !id) {
                document.getElementById('noteId').value = d.note_id;
                document.getElementById('toolsSection').style.display      = 'block';
                document.getElementById('colorSection').style.display      = 'block';
                document.getElementById('shareManagerSection').style.display = 'block';
                document.getElementById('btnTrashNote').style.display      = 'block';
                refreshLabelSelector();
            }
            document.getElementById('saveStatus').innerText = d.success ? 'Đã lưu' : 'Lỗi lưu!';
            if (d.success) liveSearch();
        });
    }, 800);
}

// ── CHIA SẺ ───────────────────────────────────────────────────
// ── CHIA SẺ GHI CHÚ (HỖ TRỢ NHIỀU NGƯỜI) ─────────────────────
function shareNote() {
    const noteId = document.getElementById('noteId').value;
    const input  = document.getElementById('share_input').value.trim();
    const perm   = document.getElementById('sharePermission').value;

    if (!noteId) return alert('Vui lòng tạo/lưu ghi chú trước khi chia sẻ!');
    if (!input)  return alert('Vui lòng nhập ít nhất một email!');

    // Xử lý nhiều email
    const emails = input.split(',').map(e => e.trim()).filter(e => e);

    if (emails.length === 0) return alert('Không tìm thấy email hợp lệ!');

    const fd = new FormData();
    fd.append('note_id', noteId);
    fd.append('permission', perm);
    fd.append('share_with', emails.join(','));   // Gửi dạng chuỗi

    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            alert(d.message || 'Đã xử lý chia sẻ!');
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
                liveSearch(); // Cập nhật danh sách
            }
        })
        .catch(() => alert('Lỗi kết nối server.'));
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
                        <span><i class="bi bi-person-check text-success"></i>
                        ${escapeHtml(u.display_name)}
                        <small class="text-muted fst-italic">(${escapeHtml(u.permission)})</small></span>
                        <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
                    </li>`;
            });
        });
}

function revokeShare(shareId) {
    if (!confirm('Bạn có chắc muốn thu hồi quyền truy cập của người này?')) return;
    const fd = new FormData();
    fd.append('share_id', shareId);
    fetch('api/revoke_share.php', { method: 'POST', body: fd })
        .then(() => loadSharedUsers(document.getElementById('noteId').value));
}

// ── MÀU / XÓA / KHÔI PHỤC / GHIM ─────────────────────────────
function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    fetch('api/change_color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&color=${encodeURIComponent(color)}`
    }).then(() => {
        document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';
        liveSearch();
    });
}

function deleteNote(action) {
    const id  = document.getElementById('noteId').value;
    const msg = action === 'trash'
        ? 'Chuyển ghi chú này vào thùng rác?'
        : 'Bạn có chắc muốn XÓA VĨNH VIỄN ghi chú này?';
    if (confirm(msg)) {
        fetch('api/delete_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}&action=${action}`
        }).then(() => closeAndReload());
    }
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    fetch('api/restore_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => { alert('Đã khôi phục ghi chú!'); closeAndReload(); });
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&is_pinned=${state}`
    }).then(() => liveSearch());
}

// ── NHÃN ──────────────────────────────────────────────────────
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list')
        .then(res => res.json())
        .then(ls => {
            const bar = document.getElementById('labelFilterBar');
            bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}"
                onclick="filterLabel(null)" ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>Tất cả</button>`;

            ls.forEach(l => {
                const safeName = escapeHtml(l.name || '');
                const escapedForJS = safeName.replace(/'/g, "\\'");
                
                bar.innerHTML += `
                    <div class="btn-group btn-group-sm shadow-sm">
                        <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}"
                            onclick="filterLabel(${l.id})" 
                            ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>
                            ${safeName}
                        </button>
                        <button class="btn btn-outline-secondary" 
                            onclick="event.stopPropagation(); renameLabel(${l.id}, '${escapedForJS}')"
                            ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" 
                            onclick="event.stopPropagation(); deleteLabel(${l.id})"
                            ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>
                            <i class="bi bi-x"></i>
                        </button>
                    </div>`;
            });

            if (typeof callback === 'function') callback();
        })
        .catch(err => {
            console.error("Lỗi load labels:", err);
            if (typeof callback === 'function') callback();
        });
}

function filterLabel(id) {
    if (currentViewMode !== 'my_notes') return;
    currentLabelId = id;
    loadFilterLabels(() => liveSearch());
}

function deleteLabel(id) {
    if (!confirm('Xóa nhãn này?')) return;
    fetch('api/manage_labels.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => {
        if (currentLabelId == id) currentLabelId = null;
        loadFilterLabels(() => liveSearch());
    });
}

function addNewLabel() {
    const n = document.getElementById('newLabelName').value.trim();
    if (!n) return;
    fetch('api/manage_labels.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(n)}`
    }).then(() => {
        document.getElementById('newLabelName').value = '';
        loadFilterLabels();
        if (document.getElementById('noteId').value) refreshLabelSelector();
    });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list')
        .then(res => res.json())
        .then(ls => {
            const s = document.getElementById('labelSelector');
            s.innerHTML = '<option value="">+ Gắn nhãn...</option>';
            ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
        });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`)
        .then(res => res.json())
        .then(ls => {
            const c = document.getElementById('noteLabelsContainer');
            c.innerHTML = '';
            ls.forEach(l => {
                c.innerHTML += `<span class="badge bg-secondary fs-6">${escapeHtml(l.name)}
                    <i class="bi bi-x-circle-fill text-white ms-1 cp" onclick="removeLabel(${nid},${l.id})"></i></span>`;
            });
        });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if (!lid) return;
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=add`
    }).then(() => { loadLabelsForNote(nid); document.getElementById('labelSelector').value = ''; liveSearch(); });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=remove`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ── KHÓA ──────────────────────────────────────────────────────
// ── KHÓA GHI CHÚ ─────────────────────────────────────────────
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (!id) return;

    if (isLockedState) {
        if (confirm('Ghi chú đang được khóa.\n\nChọn hành động:\n• OK = Gỡ khóa\n• Cancel = Đổi mật khẩu')) {
            unlockNote(id);
        } else {
            changeNotePassword(id);
        }
    } else {
        const p = prompt('Nhập mật khẩu mới để khóa ghi chú (ít nhất 4 ký tự):');
        if (!p || p.length < 4) {
            return alert('Mật khẩu phải có ít nhất 4 ký tự!');
        }
        lockNote(id, p);
    }
}

function lockNote(id, password) {
    const fd = new FormData();
    fd.append('note_id', id);
    fd.append('password', password);
    fd.append('action', 'lock');

    fetch('api/lock_note.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            isLockedState = true;
            alert('✅ Đã khóa ghi chú thành công!');
            liveSearch();
            closeAndReload();
        }
    });
}

function unlockNote(id) {
    if (!confirm('Xác nhận GỠ KHÓA ghi chú này?')) return;
    
    const fd = new FormData();
    fd.append('note_id', id);
    fd.append('action', 'unlock');

    fetch('api/lock_note.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            isLockedState = false;
            alert('✅ Đã gỡ khóa thành công!');
            liveSearch();
            closeAndReload();
        }
    });
}

function changeNotePassword(id) {
    const newPass = prompt('Nhập mật khẩu MỚI (ít nhất 4 ký tự):');
    if (!newPass || newPass.length < 4) return alert('Mật khẩu quá ngắn!');

    const fd = new FormData();
    fd.append('note_id', id);
    fd.append('password', newPass);
    fd.append('action', 'lock');

    fetch('api/lock_note.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(d => {
        if (d.success) alert('✅ Đã đổi mật khẩu thành công!');
    });
}
// ── ẢNH ───────────────────────────────────────────────────────
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    document.getElementById('saveStatus').innerText = 'Đang tải ảnh...';
    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                renderImage(d.file_path, d.image_id, 'owner');
                document.getElementById('saveStatus').innerText = 'Đã tải ảnh';
            } else {
                alert(d.message);
                document.getElementById('saveStatus').innerText = '';
            }
            document.getElementById('imageInput').value = '';
        });
}

function renderImage(path, id, perm) {
    const deleteBtn = perm === 'owner'
        ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle"
              style="width:24px;height:24px;margin-top:-8px;margin-right:-8px;"
              onclick="deleteImage(${id}, this)"><i class="bi bi-x"></i></button>`
        : '';
    document.getElementById('imagePreviewContainer').innerHTML +=
        `<div class="position-relative shadow-sm rounded">
            <img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">
            ${deleteBtn}
         </div>`;
}

function deleteImage(id, btn) {
    if (!confirm('Xóa ảnh?')) return;
    fetch('api/delete_image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(res => res.json()).then(d => {
        if (d.success) btn.parentElement.remove();
        else alert(d.message);
    });
}

// ── PROFILE ───────────────────────────────────────────────────
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('previewAvatar').src = e.target.result; };
        reader.readAsDataURL(input.files[0]);
    }
}

function saveProfile() {
    const fd   = new FormData();
    const file = document.getElementById('inputAvatar').files[0];
    if (file) fd.append('avatar', file);
    
    fd.append('font_size',   document.getElementById('settingFontSize').value);
    fd.append('theme_color', document.getElementById('settingTheme').value);
    fd.append('note_color',  document.getElementById('settingNoteColor').value);

    fetch('api/update_profile.php', { 
        method: 'POST', 
        body: fd 
    })
    .then(res => {
        if (!res.ok) throw new Error('Server error: ' + res.status);
        return res.json();
    })
    .then(data => {
        if (data.success) {
            alert('✅ Đã lưu cấu hình thành công!');
            location.reload();
        } else {
            alert('❌ ' + (data.message || 'Lỗi không xác định'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi kết nối server! Kiểm tra console để xem chi tiết.');
    });
}
// ── TIỆN ÍCH ──────────────────────────────────────────────────
function setView(v) {
    document.getElementById('notesContainer').className =
        v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function closeAndReload() { noteModal.hide(); liveSearch(); }

function escapeHtml(s) {
    if (!s) return '';
    return s.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
// Đổi tên nhãn
function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);

    if (
        newName === null ||
        newName.trim() === '' ||
        newName === currentName
    ) return;

    fetch('api/manage_labels.php?action=rename', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `id=${id}&name=${encodeURIComponent(newName.trim())}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {

            loadFilterLabels();

            const noteId = document.getElementById('noteId').value;

            if (noteId) {
                loadLabelsForNote(noteId);
            }

            liveSearch();

        } else {
            alert(data.message || 'Đổi tên thất bại');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Lỗi đổi tên nhãn');
    });
}
// Realtime simulation cho ghi chú được chia sẻ có quyền Edit
let autoRefreshInterval = null;

function startAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    
    if (currentViewMode === 'shared' && currentPermission === 'edit') {
        autoRefreshInterval = setInterval(() => {
            liveSearch(); // Tự động refresh danh sách
        }, 3000); // Mỗi 3 giây
    }
}
function closeAndReload() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    noteModal.hide(); 
    liveSearch();
}
</script>
</body>
</html>
        //
        |
        - reset_password.php
        //code//
        <?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'database.php';
require_once 'mail_config.php';

$step = 1;
$message = '';
$token_verified = false;   // Flag mới

// XỬ LÝ TOKEN TỪ LINK EMAIL
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $step = 2;
        $token_verified = true;           // Đánh dấu đã verify qua link
        // Có thể xóa token sau khi verify (tùy chọn)
        // $pdo->prepare("UPDATE users SET reset_token = NULL WHERE id = ?")->execute([$user['id']]);
    } else {
        $message = "<div class='alert alert-danger'>Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn!</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_reset') {
        $email = trim($_POST['email'] ?? '');
        $type  = $_POST['type'] ?? 'otp';

        $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
                ->execute([$reset_token, $expiry, $user['id']]);

            if ($type === 'link') {
                $sent = sendResetLinkEmail($email, $user['display_name'], $reset_token);
                $msg = "Link đặt lại mật khẩu đã được gửi đến email của bạn!";
            } else {
                $otp = rand(100000, 999999);
                $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?")->execute([$otp, $user['id']]);
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
            $message = "<div class='alert alert-danger'>Email không tồn tại!</div>";
        }
    } 
    elseif ($action === 'reset_password') {
        $input = trim($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $user_id = $_SESSION['reset_user_id'] ?? 0;

        if (strlen($new_pass) < 6) {
            $message = "<div class='alert alert-danger'>Mật khẩu phải có ít nhất 6 ký tự!</div>";
            $step = 2;
        } elseif ($user_id == 0) {
            $message = "<div class='alert alert-danger'>Phiên hết hạn!</div>";
            $step = 1;
        } else {
            $stmt = $pdo->prepare("SELECT reset_token, reset_token_expiry FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // Nếu đã verify qua link thì không cần check token nữa
            $is_valid = $token_verified || 
                       ($user && $user['reset_token'] == $input && date('Y-m-d H:i:s') <= $user['reset_token_expiry']);

            if ($is_valid) {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")
                    ->execute([$hashed, $user_id]);

                unset($_SESSION['reset_user_id']);
                $message = "<div class='alert alert-success'><strong>Đổi mật khẩu thành công!</strong><br><a href='login.php' class='btn btn-primary mt-2'>Đăng nhập ngay</a></div>";
                $step = 3;
            } else {
                $message = "<div class='alert alert-danger'>Mã không đúng hoặc đã hết hạn!</div>";
                $step = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7f6; display:flex; align-items:center; min-height:100vh; }
        .card { border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
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
                    <input type="hidden" name="action" value="send_reset">
                    <div class="mb-3">
                        <label class="form-label">Nhập email tài khoản</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Chọn phương thức:</label><br>
                        <input type="radio" name="type" value="otp" checked> OTP<br>
                        <input type="radio" name="type" value="link"> Link Reset
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Gửi</button>
                </form>
                <?php endif; ?>

                <?php if ($step == 2): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <?php if (!$token_verified): ?>
                    <div class="mb-3">
                        <label class="form-label">Nhập mã OTP hoặc Token</label>
                        <input type="text" name="otp" class="form-control" required>
                    </div>
                    <?php else: ?>
                        <!-- Ẩn trường nhập token nếu đã verify qua link -->
                        <input type="hidden" name="otp" value="verified_via_link">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Đổi mật khẩu</button>
                </form>
                <?php endif; ?>

                <?php if ($step == 3): ?>
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">→ Đến trang đăng nhập</a>
                </div>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php">← Quay lại đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
        </html>
        //
        |
        - Rubrik.docx 
        |
        - service-worker.js
