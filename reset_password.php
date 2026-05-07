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