<?php
// websocket/server.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../database.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

use App\NoteWebSocket;

$wsCfg    = require __DIR__ . '/ws_secret.php';
$wsSecret = $wsCfg['secret'] ?? '';

if ($wsSecret === '') {
    fwrite(STDERR, "ERROR: websocket/ws_secret.php must define a non-empty secret.\n");
    exit(1);
}

// =====================================================
// WEBSOCKET SERVER
// =====================================================

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NoteWebSocket($pdo, $wsSecret)
        )
    ),
    8080
);

echo "🚀 WebSocket running at ws://localhost:8080\n";

$server->run();