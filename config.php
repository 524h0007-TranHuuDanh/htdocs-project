<?php
// Đảm bảo không sử dụng hardcoded ports hay hostnames trong source code 
define('BASE_URL', '/'); // Vì project đặt trực tiếp trong htdocs theo yêu cầu [cite: 118, 119]

// Cấu hình Mail (Dành cho Phase 2: Kích hoạt & Reset password) [cite: 21, 25]
// Bạn sẽ cần thư viện PHPMailer hoặc tương tự ở Phase sau.
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
?>