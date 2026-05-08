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