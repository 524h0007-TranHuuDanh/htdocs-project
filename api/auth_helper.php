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