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
    ensure_session_csrf_token();
}

/**
 * Ensures a session-bound CSRF secret exists for authenticated flows.
 */
function ensure_session_csrf_token(): void {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Rejects POST when CSRF token is missing, wrong type, or not hash_equals to session value.
 */
function require_valid_csrf_post(): void {
    $sent = $_POST['csrf_token'] ?? null;
    $sess  = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sess) || $sess === '' || !is_string($sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF).']);
        exit;
    }
    if (!hash_equals($sess, $sent)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ (CSRF).']);
        exit;
    }
}