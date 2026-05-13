<?php
/**
 * Short-lived signed token for WebSocket auth (session-bound; not sent with page HTML).
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$cfg    = require __DIR__ . '/../websocket/ws_secret.php';
$secret = $cfg['secret'] ?? '';

if ($secret === '') {
    echo json_encode(['success' => false, 'message' => 'WS chưa cấu hình']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Phiên không hợp lệ']);
    exit;
}

$exp     = time() + 600;
$payload = json_encode(
    [
        'uid'   => $userId,
        'exp'   => $exp,
        'nonce' => bin2hex(random_bytes(8)),
    ],
    JSON_UNESCAPED_SLASHES
);

$sig   = hash_hmac('sha256', $payload, $secret, true);
$token = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=')
    . '.'
    . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

echo json_encode([
    'success' => true,
    'token'   => $token,
    'exp'     => $exp,
]);
