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