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