<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $noteSubscriptions = []; // note_id => [resourceId => ['conn' => conn, 'user_name' => str]]

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->send(json_encode(['type' => 'ping']));
        $conn->user_id   = 0;
        $conn->user_name = 'Unknown';
        echo "[WS] New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        switch ($data['type']) {

            case 'auth':
                $uid  = intval($data['user_id'] ?? 0);
                $name = $data['user_name'] ?? 'User';
                if ($uid > 0) {
                    $from->user_id   = $uid;
                    $from->user_name = $name;
                    $from->send(json_encode(['type' => 'auth_success']));
                    echo "[WS] User $uid ($name) authenticated\n";
                }
                break;

            case 'join_note':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && $from->user_id > 0) {
                    // Thêm vào phòng
                    $this->noteSubscriptions[$note_id][$from->resourceId] = [
                        'conn'      => $from,
                        'user_name' => $from->user_name
                    ];
                    echo "[WS] User {$from->user_id} joined note $note_id\n";

                    // Broadcast danh sách người đang xem
                    $this->broadcastPresence($note_id);
                }
                break;

            case 'leave_note':
                $note_id = intval($data['note_id'] ?? 0);
                $this->removeFromNote($from, $note_id);
                break;

            case 'update':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && isset($this->noteSubscriptions[$note_id])) {

                    $broadcastData = [
                        'type'       => 'update',
                        'note_id'    => $note_id,
                        'user_name'  => $from->user_name,
                        'title'      => $data['title'] ?? null,
                        'content'    => $data['content'] ?? null,
                        'sender_id'  => $from->resourceId,
                        'timestamp'  => time()
                    ];

                    // Broadcast cho tất cả người KHÁC trong note (không echo lại người gửi)
                    foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
                        if ($info['conn'] !== $from) {
                            $info['conn']->send(json_encode($broadcastData));
                        }
                    }
                }
                break;
        }
    }

    /**
     * Broadcast danh sách người đang xem ghi chú
     */
    private function broadcastPresence(int $note_id) {
        if (!isset($this->noteSubscriptions[$note_id])) return;

        $users = array_values(array_map(
            fn($info) => $info['user_name'],
            $this->noteSubscriptions[$note_id]
        ));

        $payload = json_encode([
            'type'     => 'presence',
            'note_id'  => $note_id,
            'users'    => $users
        ]);

        foreach ($this->noteSubscriptions[$note_id] as $info) {
            $info['conn']->send($payload);
        }
    }

    private function removeFromNote(ConnectionInterface $conn, int $note_id) {
        if ($note_id && isset($this->noteSubscriptions[$note_id])) {
            unset($this->noteSubscriptions[$note_id][$conn->resourceId]);

            if (empty($this->noteSubscriptions[$note_id])) {
                unset($this->noteSubscriptions[$note_id]);
            } else {
                $this->broadcastPresence($note_id);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Xóa khỏi tất cả các note đang tham gia
        foreach (array_keys($this->noteSubscriptions) as $note_id) {
            $this->removeFromNote($conn, (int)$note_id);
        }
        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
