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