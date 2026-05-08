<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $noteConnections = []; // note_id => [connections]

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || empty($data['note_id'])) return;

        $noteId = $data['note_id'];

        echo "Broadcast update for note {$noteId}\n";

        // Broadcast đến tất cả client khác
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send(json_encode([
                    'type'      => 'update',
                    'note_id'   => $noteId,
                    'title'     => $data['title'] ?? '',
                    'content'   => $data['content'] ?? '',
                    'user'      => $data['user_name'] ?? 'Someone'
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection closed ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}