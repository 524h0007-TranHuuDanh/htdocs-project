<?php
// websocket/server.php

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

use App\NoteWebSocket;

// =====================================================
// WEBSOCKET SERVER
// =====================================================

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NoteWebSocket()
        )
    ),
    8080
);

echo "🚀 WebSocket running at ws://localhost:8080\n";

$server->run();