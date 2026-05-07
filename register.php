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