<?php
/**
 * Shared secret for WebSocket HMAC tokens (must match NoteWebSocket + api/ws_token.php).
 * Override in production: set env NOTEAPP_WS_SECRET to a long random string.
 */
return [
    'secret' => getenv('NOTEAPP_WS_SECRET') ?: 'noteapp-ws-local-dev-secret-min-length-32-chars!',
];
