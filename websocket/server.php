<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\NoteWebSocket;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NoteWebSocket()
        )
    ),
    8080  // Port WebSocket - KHÔNG đổi nếu chưa biết
);

echo "🚀 WebSocket Server NoteApp đang chạy trên ws://localhost:8080\n";
echo "Nhấn Ctrl + C để dừng server...\n";

$server->run();