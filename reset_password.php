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
                    <form method="POST" id="resetFormStep1">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="send_reset">

                        <div class="mb-3">
                            <label class="form-label">Nhập email tài khoản</label>
                            <input type="email" name="email" id="resetEmailInput" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Chọn phương thức khôi phục:</label><br>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="otp" id="typeOtp" checked>
                                <label class="form-check-label" for="typeOtp">Gửi mã OTP</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" value="link" id="typeLink">
                                <label class="form-check-label" for="typeLink">Gửi link đặt lại mật khẩu</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Tiếp tục</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">← Quay lại đăng nhập</a>
                    </div>

                    <script>
                        (function() {
                            const urlParams = new URLSearchParams(window.location.search);
                            const emailFromProfile = urlParams.get('email');
                            if (emailFromProfile) {
                                const emailInput = document.getElementById('resetEmailInput');
                                if (emailInput) {
                                    emailInput.value = emailFromProfile;
                                    // Không khóa readonly để người dùng có thể sửa nếu nhầm
                                }
                            }
                        })();
                    </script>

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

                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">← Quay lại đăng nhập</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>