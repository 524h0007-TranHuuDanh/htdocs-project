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
                    $_SESSION['email']        = $email;   // ← ĐÃ SỬA: thêm email vào session
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