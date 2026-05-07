Cấu trúc thư mục:
HTDOCS - api - auth_helper.php
               //code//
               <?php
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                function is_logged_in() {
                    return isset($_SESSION['user_id']);
                }

                function check_login() {
                    if (!is_logged_in()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
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
                //
                |
                - pin_note.php
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
                // SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
                require_once 'auth_helper.php';
                require_once '../database.php';
                check_login();

                header('Content-Type: application/json');

                $user_id   = $_SESSION['user_id'];
                $keyword   = $_GET['q']        ?? '';
                $label_id  = $_GET['label_id'] ?? null;
                $view_mode = $_GET['view']     ?? 'my_notes';

                $params     = [];
                $searchTerm = "%$keyword%";

                try {
                    if ($view_mode === 'shared') {
                        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                        $meStmt->execute([$user_id]);
                        $my_email = $meStmt->fetchColumn();

                        $sql = "SELECT n.*, sn.permission, u.display_name AS owner_name
                                FROM shared_notes sn
                                JOIN notes n  ON sn.note_id  = n.id
                                JOIN users u  ON n.user_id   = u.id
                                WHERE sn.recipient_email = ?
                                AND n.user_id != ?
                                AND n.is_trashed = 0";
                        $params[] = $my_email;
                        $params[] = $user_id;

                    } elseif ($view_mode === 'trash') {
                        $sql      = "SELECT n.* FROM notes n WHERE n.user_id = ? AND n.is_trashed = 1";
                        $params[] = $user_id;

                    } else {
                        // my_notes
                        $sql      = "SELECT n.* FROM notes n WHERE n.user_id = ? AND n.is_trashed = 0";
                        $params[] = $user_id;
                        if ($label_id && $label_id !== 'null') {
                            $sql     .= " AND n.id IN (SELECT note_id FROM note_labels WHERE label_id = ?)";
                            $params[] = intval($label_id);
                        }
                    }

                    $sql     .= " AND (n.title LIKE ? OR n.content LIKE ?) ORDER BY n.is_pinned DESC, n.updated_at DESC";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Ẩn nội dung ghi chú bị khóa
                    foreach ($notes as &$note) {
                        if (!empty($note['password_hash'])) {
                            $note['is_locked'] = 1;
                            $note['title']     = '🔒 Ghi chú bí mật';
                            $note['content']   = 'Nhập mật khẩu để xem...';
                        } else {
                            $note['is_locked'] = 0;
                        }
                        unset($note['password_hash']);
                    }

                    echo json_encode($notes);

                } catch (PDOException $e) {
                    echo json_encode([]);
                }
                //
                |
                - set_note_label.php
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
                //
                |
                - share_note.php
                //code//
                <?php
                // api/share_note.php
                // SỬA WARN: Tìm người nhận theo cả email lẫn display_name (khớp với UI)
                require_once 'auth_helper.php';
                require_once '../database.php';
                check_login();

                header('Content-Type: application/json');

                $sender_id  = $_SESSION['user_id'];
                $note_id    = intval($_POST['note_id']    ?? 0);
                $share_with = trim($_POST['share_with']   ?? '');
                $permission = $_POST['permission']        ?? 'read';

                if (empty($share_with)) {
                    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập Email hoặc Tên người nhận!']);
                    exit;
                }

                if (!in_array($permission, ['read', 'edit'])) {
                    $permission = 'read';
                }

                try {
                    // SỬA: Tìm người nhận theo email HOẶC display_name (case-insensitive)
                    $stmt = $pdo->prepare(
                        "SELECT id, email, display_name FROM users
                        WHERE email = ? OR display_name = ?
                        LIMIT 1"
                    );
                    $stmt->execute([$share_with, $share_with]);
                    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$receiver) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Không tìm thấy người dùng với email/tên: "' . htmlspecialchars($share_with) . '"'
                        ]);
                        exit;
                    }

                    if ($receiver['id'] == $sender_id) {
                        echo json_encode(['success' => false, 'message' => 'Bạn không thể tự chia sẻ cho chính mình!']);
                        exit;
                    }

                    // Kiểm tra ghi chú thuộc về người gửi
                    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
                    $noteCheck->execute([$note_id, $sender_id]);
                    if (!$noteCheck->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
                        exit;
                    }

                    // Dùng email thực của người nhận (trường hợp user nhập display_name)
                    $recipient_email = $receiver['email'];

                    // Kiểm tra đã chia sẻ chưa
                    $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
                    $check->execute([$note_id, $recipient_email]);

                    if ($check->fetch()) {
                        // Cập nhật permission nếu đã tồn tại
                        $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
                        $update->execute([$permission, $note_id, $recipient_email]);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Đã cập nhật quyền cho ' . htmlspecialchars($receiver['display_name']) . '.'
                        ]);
                    } else {
                        $insert = $pdo->prepare(
                            "INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) VALUES (?, ?, ?, ?)"
                        );
                        $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
                        echo json_encode([
                            'success' => true,
                            'message' => 'Đã chia sẻ thành công cho ' . htmlspecialchars($receiver['display_name']) . '!'
                        ]);
                    }

                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
                }
                //
                |
                - update_profile.php
                //code//
                <?php
                require_once 'auth_helper.php';
                require_once '../database.php';
                check_login();

                header('Content-Type: application/json');

                $user_id = $_SESSION['user_id'];
                $font_size = $_POST['font_size'] ?? '16px';
                $theme_color = $_POST['theme_color'] ?? 'light';
                $avatar_path = $_SESSION['avatar'] ?? 'default-avatar.png';

                try {
                    // 1. Xử lý upload Avatar nếu có file gửi lên
                    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                        if (in_array($file_extension, $allowed_extensions)) {
                            // Tạo thư mục nếu chưa có
                            $upload_dir = '../uploads/avatars/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }

                            $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
                            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_filename)) {
                                $avatar_path = 'uploads/avatars/' . $new_filename;
                            }
                        }
                    }

                    // 2. Cập nhật vào Database
                    // Lưu ý: Đảm bảo bảng users của bạn đã có đủ các cột: avatar, font_size, theme_color
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ?, font_size = ?, theme_color = ? WHERE id = ?");
                    $stmt->execute([$avatar_path, $font_size, $theme_color, $user_id]);

                    // 3. Cập nhật lại Session để giao diện thay đổi ngay lập tức
                    $_SESSION['avatar'] = $avatar_path;
                    $_SESSION['font_size'] = $font_size;
                    $_SESSION['theme_color'] = $theme_color;

                    echo json_encode([
                        'success' => true, 
                        'message' => 'Cập nhật thành công',
                        'avatar' => $avatar_path
                    ]);

                } catch (PDOException $e) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Lỗi Database: ' . $e->getMessage()
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
        require_once 'database.php';

        if (isset($_GET['token'])) {
            $token = $_GET['token'];
            $stmt = $pdo->prepare("UPDATE users SET is_activated = 1, activation_token = NULL WHERE activation_token = ?");
            $stmt->execute([$token]);

            if ($stmt->rowCount() > 0) {
                echo "Tài khoản đã được kích hoạt! <a href='login.php'>Đăng nhập ngay</a>";
            } else {
                echo "Token không hợp lệ hoặc tài khoản đã kích hoạt.";
            }
        }
        ?>
        //
        |
        - composer.json
        //code//
        {
            "require": {
                "phpmailer/phpmailer": "^7.0"
            }
        }
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

        $user_font_size = $_SESSION['font_size']    ?? '16px';
        $user_theme     = $_SESSION['theme_color']  ?? 'light';
        $user_avatar    = $_SESSION['avatar']       ?? 'default-avatar.png';
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
                .nav-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid white; cursor: pointer; transition: transform 0.2s; }
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
                    <img src="<?= htmlspecialchars($user_avatar) ?>" class="nav-avatar shadow-sm"
                        onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()"
                        title="Cài đặt tài khoản">
                    <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
                </div>
            </div>
        </nav>

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
                                    placeholder="Nhập Email hoặc Tên người nhận...">
                                <select id="sharePermission" class="form-select" style="max-width: 130px;">
                                    <option value="read">Chỉ xem</option>
                                    <option value="edit">Cho phép sửa</option>
                                </select>
                                <button class="btn btn-success" onclick="shareNote()">Gửi</button>
                            </div>
                            <ul id="sharedUsersList" class="list-group list-group-flush small"></ul>
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

        <!-- Modal profile -->
        <div class="modal fade" id="profileModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>"
                            class="rounded-circle mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                        <div class="mb-4">
                            <label class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                <i class="bi bi-camera"></i> Đổi ảnh đại diện
                                <input type="file" id="inputAvatar" hidden accept="image/*" onchange="previewImage(this)">
                            </label>
                        </div>
                        <hr>
                        <div class="row text-start g-3 mt-2">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">Kích thước chữ</label>
                                <select id="settingFontSize" class="form-select">
                                    <option value="14px" <?= $user_font_size=='14px'?'selected':'' ?>>Nhỏ</option>
                                    <option value="16px" <?= $user_font_size=='16px'?'selected':'' ?>>Vừa (Mặc định)</option>
                                    <option value="18px" <?= $user_font_size=='18px'?'selected':'' ?>>Lớn</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">Giao diện (Theme)</label>
                                <select id="settingTheme" class="form-select">
                                    <option value="light" <?= $user_theme=='light'?'selected':'' ?>>Sáng</option>
                                    <option value="dark"  <?= $user_theme=='dark' ?'selected':'' ?>>Tối</option>
                                </select>
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
        let typingTimer, searchTimer;
        let currentLabelId  = null;
        let isLockedState   = false;
        let currentViewMode = 'my_notes';
        let currentPermission = 'owner';

        document.addEventListener('DOMContentLoaded', () => {
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
                const shareBadge = ownerName
                    ? `<span class="badge bg-info text-dark position-absolute bottom-0 start-0 m-2"><i class="bi bi-person"></i> Từ: ${escapeHtml(ownerName)}</span>`
                    : '';

                // SỬA: Dùng data-attribute thay vì inline onclick với string escaping
                const card = document.createElement('div');
                card.className = 'card note-card h-100';
                card.style.cssText = bgColor;

                const body = document.createElement('div');
                body.className = 'card-body position-relative pb-4';
                body.dataset.id         = n.id;
                body.dataset.title      = n.title    || '';
                body.dataset.content    = n.content  || '';
                body.dataset.isLocked   = n.is_locked;
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
                            <i class="bi ${pinClass} fs-5"></i></button>`
                        : ''}
                    <h5 class="card-title text-truncate pe-4 ${n.is_locked ? 'text-warning' : ''}">${escapeHtml(n.title) || 'Không tiêu đề'}</h5>
                    <p class="card-text text-muted text-truncate" style="white-space:pre-wrap;">${escapeHtml(n.content) || '...'}</p>
                    ${shareBadge}`;

                card.appendChild(body);
                container.appendChild(card);
            });
        }

        // ── MỞ GHI CHÚ ───────────────────────────────────────────────
        function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
            if (isLocked && currentViewMode !== 'trash') {
                const pwd = prompt('Nhập mật khẩu để xem:');
                if (!pwd) return;
                const fd = new FormData();
                fd.append('note_id', id);
                fd.append('password', pwd);
                fetch('api/verify_note.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(d => {
                        if (d.success) {
                            isLockedState = true;
                            openNoteModal(id, d.title, d.content, d.color, d.permission, ownerName);
                        } else {
                            alert(d.message);
                        }
                    });
            } else {
                isLockedState = false;
                openNoteModal(id, title, content, color, permission, ownerName);
            }
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
                notice.innerHTML = `Được chia sẻ bởi <b>${escapeHtml(ownerName)}</b> | Quyền của bạn: <b>${permission === 'read' ? 'Chỉ xem' : 'Có thể sửa'}</b>`;
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
        function shareNote() {
            const noteId    = document.getElementById('noteId').value;
            const shareWith = document.getElementById('share_input').value.trim();
            const perm      = document.getElementById('sharePermission').value;
            if (!noteId)    return alert('Vui lòng ghi nội dung ghi chú và chờ lưu tự động trước khi chia sẻ!');
            if (!shareWith) return alert('Vui lòng nhập Email hoặc Tên người nhận!');
            const fd = new FormData();
            fd.append('note_id',    noteId);
            fd.append('share_with', shareWith);
            fd.append('permission', perm);
            fetch('api/share_note.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(d => {
                    alert(d.message);
                    if (d.success) {
                        document.getElementById('share_input').value = '';
                        loadSharedUsers(noteId);
                    }
                })
                .catch(() => alert('Lỗi kết nối tới server.'));
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
                }).then(closeAndReload);
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
            }).then(liveSearch);
        }

        // ── NHÃN ──────────────────────────────────────────────────────
        // SỬA WARN: loadFilterLabels nhận callback để tránh race condition với liveSearch
        function loadFilterLabels(callback) {
            fetch('api/manage_labels.php?action=list')
                .then(res => res.json())
                .then(ls => {
                    const bar = document.getElementById('labelFilterBar');
                    bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}"
                        onclick="filterLabel(null)" ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>Tất cả</button>`;
                    ls.forEach(l => {
                        bar.innerHTML += `
                            <div class="btn-group btn-group-sm shadow-sm">
                                <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}"
                                    onclick="filterLabel(${l.id})" ${currentViewMode !== 'my_notes' ? 'disabled' : ''}>${escapeHtml(l.name)}</button>
                                <button class="btn btn-outline-danger" onclick="deleteLabel(${l.id})"
                                    ${currentViewMode !== 'my_notes' ? 'disabled' : ''}><i class="bi bi-x"></i></button>
                            </div>`;
                    });
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
        function toggleLock() {
            const id = document.getElementById('noteId').value;
            if (isLockedState) {
                if (!confirm('Gỡ mật khẩu?')) return;
                fetch('api/lock_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `note_id=${id}&action=unlock`
                }).then(() => {
                    isLockedState = false;
                    document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
                    liveSearch();
                });
            } else {
                const p = prompt('Mật khẩu mới:');
                if (!p) return;
                fetch('api/lock_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `note_id=${id}&password=${encodeURIComponent(p)}&action=lock`
                }).then(() => {
                    isLockedState = true;
                    document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
                    liveSearch();
                    alert('Đã khóa!');
                });
            }
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
            fetch('api/update_profile.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) { alert('Đã lưu cấu hình thành công!'); location.reload(); }
                    else alert('Có lỗi xảy ra: ' + (data.message || 'Không thể cập nhật'));
                })
                .catch(() => alert('Đã có lỗi kết nối đến server!'));
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
        </script>
        </body>
        </html>
        //
        |
        - login.php
        //code//
        <?php
        session_start();
        require_once 'database.php'; // Đã bỏ api/ vì file nằm cùng thư mục

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (!empty($email) && !empty($password)) {
                // Tìm user theo email (khớp với database của bạn)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Kiểm tra mật khẩu (Sử dụng password_hash thay vì password)
                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['display_name'] = $user['display_name'];
                    $_SESSION['avatar'] = $user['avatar'] ?? 'default-avatar.png';
                    $_SESSION['font_size'] = $user['font_size'] ?? '16px';
                    $_SESSION['theme_color'] = $user['theme_color'] ?? 'light';
                    
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Email hoặc mật khẩu không chính xác!";
                }
            } else {
                $error = "Vui lòng nhập đầy đủ thông tin!";
            }
        }
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Đăng nhập - NoteApp</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f4f7f6; height: 100vh; display: flex; align-items: center; }
                .login-card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card login-card p-4">
                            <h2 class="text-center mb-4 fw-bold text-primary">Đăng Nhập</h2>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger small py-2"><?= $error ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="example@gmail.com" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Mật khẩu</label>
                                    <input type="password" name="password" class="form-control" placeholder="******" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Đăng nhập</button>
                            </form>

                            <div class="mt-4 text-center">
                                <a href="reset_password.php" class="text-decoration-none small text-muted">Quên mật khẩu?</a>
                                <hr>
                                <p class="small mb-0">Chưa có tài khoản? <a href="register.php" class="text-decoration-none">Đăng ký ngay</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        //
        |
        - logout.php
        //code//
        <?php
        session_start();
        session_destroy();
        header("Location: login.php");
        exit();
        ?>
        //
        |
        - manifest.json
        |
        - register.php
        //code//
        <?php
        require_once 'database.php';
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'];
            $display_name = $_POST['display_name'];
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

            try {
                // Lưu đúng vào các cột email, display_name, password_hash
                $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$email, $display_name, $password]);
                header("Location: login.php?registered=1");
            } catch (PDOException $e) {
                $message = "Lỗi: Email đã tồn tại hoặc dữ liệu không hợp lệ!";
            }
        }
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
                            <h3 class="card-title text-center">Đăng ký tài khoản</h3>
                            <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
                            <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>
                            <form method="POST">
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
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label>Xác nhận mật khẩu</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Đăng ký</button>
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
        //
        |
        - reset_password.php
        //code//
        <?php
        session_start();
        date_default_timezone_set('Asia/Ho_Chi_Minh'); // Thiết lập múi giờ Việt Nam
        require_once 'database.php'; 

        $step = 1;
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            // BƯỚC 1: NHẬP EMAIL
            if ($action === 'request_otp') {
                $user_input = trim($_POST['user_input']);
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
                $stmt->execute([$user_input]);
                $user = $stmt->fetch();

                if ($user) {
                    $otp = rand(100000, 999999);
                    // Lưu thời gian hết hạn là 15 phút sau (tính bằng giây/timestamp)
                    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    $stmt = $pdo->prepare("UPDATE users SET reset_otp = ?, otp_expiry = ? WHERE id = ?");
                    $stmt->execute([$otp, $expiry, $user['id']]);
                    
                    $_SESSION['reset_user_id'] = $user['id'];
                    $step = 2;
                    $message = "<div class='alert alert-info shadow-sm'>Mã OTP của bạn là: <b class='fs-4 text-primary'>$otp</b></div>";
                } else {
                    $message = "<div class='alert alert-danger'>Email này không tồn tại!</div>";
                }
            } 
            // BƯỚC 2: XÁC THỰC OTP
            elseif ($action === 'verify_and_reset') {
                $otp = trim($_POST['otp']);
                $new_pass = $_POST['new_password'];
                $user_id = $_SESSION['reset_user_id'] ?? 0;

                // Lấy dữ liệu OTP từ database để so sánh bằng PHP (tránh lệch múi giờ SQL)
                $stmt = $pdo->prepare("SELECT reset_otp, otp_expiry FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $current_time = date('Y-m-d H:i:s');

                // Kiểm tra mã khớp VÀ thời gian hiện tại phải nhỏ hơn thời gian hết hạn
                if ($user && $user['reset_otp'] == $otp && $current_time <= $user['otp_expiry']) {
                    $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                    
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_otp = NULL, otp_expiry = NULL WHERE id = ?");
                    $stmt->execute([$hash, $user_id]);
                    
                    $message = "<div class='alert alert-success shadow-sm'>Đổi mật khẩu thành công! <a href='login.php' class='fw-bold'>Đăng nhập</a></div>";
                    $step = 3;
                    unset($_SESSION['reset_user_id']);
                } else {
                    $step = 2;
                    $message = "<div class='alert alert-danger shadow-sm'>Mã OTP không đúng hoặc đã hết hạn!</div>";
                }
            }
        }
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <title>Quên mật khẩu</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { background: #f4f7f6; display: flex; align-items: center; height: 100vh; }
                .card { border-radius: 15px; border: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card shadow-lg p-4">
                            <h3 class="text-center mb-4 text-primary">Khôi phục</h3>
                            <?= $message ?>

                            <?php if ($step == 1): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="request_otp">
                                <div class="mb-3">
                                    <label class="form-label">Nhập Email của bạn</label>
                                    <input type="email" name="user_input" class="form-control" placeholder="example@gmail.com" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Gửi mã OTP</button>
                            </form>
                            <?php endif; ?>

                            <?php if ($step == 2): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="verify_and_reset">
                                <div class="mb-3">
                                    <label class="form-label">Mã OTP (6 số)</label>
                                    <input type="text" name="otp" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu mới</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="6">
                                </div>
                                <button type="submit" class="btn btn-success w-100">Cập nhật mật khẩu</button>
                            </form>
                            <?php endif; ?>

                            <div class="text-center mt-3">
                                <a href="login.php" class="text-decoration-none small text-muted">Quay lại đăng nhập</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        //
        |
        - Rubrik.docx 
        |
        - service-worker.js
