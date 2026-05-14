<?php

namespace App;

use PDO;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {

    protected $clients;
    protected $noteSubscriptions = []; // note_id => [resourceId => ['conn' => conn, 'user_name' => str]]
    /** @var PDO */
    protected $pdo;
    protected $wsSecret;

    public function __construct(PDO $pdo, string $wsSecret) {
        $this->clients   = new \SplObjectStorage;
        $this->pdo       = $pdo;
        $this->wsSecret  = $wsSecret;
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
                $this->handleAuth($from, $data);
                break;
            case 'join_note':
                $this->handleJoinNote($from, $data);
                break;
            case 'leave_note':
                $note_id = intval($data['note_id'] ?? 0);
                $this->removeFromNote($from, $note_id);
                break;
            case 'update':
                $this->handleUpdate($from, $data);
                break;
            // ========== THÊM MỚI: xử lý màu sắc và hình ảnh ==========
            case 'color_update':
                $this->handleColorUpdate($from, $data);
                break;
            case 'image_added':
                $this->handleImageAdded($from, $data);
                break;
            case 'image_deleted':
                $this->handleImageDeleted($from, $data);
                break;
        }
    }

    /**
     * Xác thực token và gán user_id, user_name cho connection.
     */
    private function handleAuth(ConnectionInterface $from, array $data) {
        $token = $data['token'] ?? '';
        if (!is_string($token) || $token === '') {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Thiếu token']));
            return;
        }

        $uid = $this->verifyWsToken($token);
        if ($uid === null) {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Token không hợp lệ hoặc đã hết hạn']));
            return;
        }

        $stmt = $this->pdo->prepare('SELECT id, display_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $from->send(json_encode(['type' => 'auth_error', 'message' => 'Người dùng không tồn tại']));
            return;
        }

        $from->user_id   = (int) $row['id'];
        $from->user_name = $row['display_name'] !== '' && $row['display_name'] !== null
            ? $row['display_name']
            : 'User';

        $from->send(json_encode(['type' => 'auth_success']));
        echo "[WS] User {$from->user_id} ({$from->user_name}) authenticated via token\n";
    }

    /**
     * @return int|null user id on success
     */
    private function verifyWsToken(string $token) {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) return null;

        list($payloadB64, $sigB64) = $parts;
        $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
        if ($payloadJson === false || $payloadJson === '') return null;

        $expectedSig = hash_hmac('sha256', $payloadJson, $this->wsSecret, true);
        $sig = base64_decode(strtr($sigB64, '-_', '+/'), true);
        if (!is_string($sig) || !hash_equals($expectedSig, $sig)) return null;

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || empty($payload['uid']) || empty($payload['exp'])) return null;

        if ((int) $payload['exp'] < time()) return null;

        return (int) $payload['uid'];
    }

    private function handleJoinNote(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;

        if (!$this->userCanAccessNote((int) $from->user_id, $note_id)) {
            $from->send(json_encode([
                'type'    => 'join_denied',
                'note_id' => $note_id,
                'message' => 'Không có quyền tham gia ghi chú này',
            ]));
            echo "[WS] join_denied user={$from->user_id} note=$note_id\n";
            return;
        }

        $this->noteSubscriptions[$note_id][$from->resourceId] = [
            'conn'      => $from,
            'user_name' => $from->user_name,
        ];
        echo "[WS] User {$from->user_id} joined note $note_id\n";

        $this->broadcastPresence($note_id);
    }

    /**
     * Owner or any active share recipient (same rules as viewing note).
     */
    private function userCanAccessNote(int $userId, int $noteId) {
        $sql = 'SELECT 1 FROM notes n WHERE n.id = ? AND n.is_trashed = 0 AND (
            n.user_id = ?
            OR EXISTS (
                SELECT 1 FROM shared_notes sn
                INNER JOIN users u ON u.id = ? AND sn.recipient_email = u.email
                WHERE sn.note_id = n.id
            )
        ) LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([$noteId, $userId, $userId]);
        return (bool) $st->fetchColumn();
    }

    /** Owner or shared with edit permission (matches client realtime for editors). */
    private function userCanEditNote(int $userId, int $noteId) {
        $st = $this->pdo->prepare('SELECT 1 FROM notes WHERE id = ? AND user_id = ? LIMIT 1');
        $st->execute([$noteId, $userId]);
        if ($st->fetchColumn()) return true;

        $st = $this->pdo->prepare(
            "SELECT 1 FROM shared_notes sn
             INNER JOIN users u ON u.id = ? AND sn.recipient_email = u.email
             WHERE sn.note_id = ? AND sn.permission = 'edit' LIMIT 1"
        );
        $st->execute([$userId, $noteId]);
        return (bool) $st->fetchColumn();
    }

    private function fetchAuthoritativeNoteVersion(int $noteId) {
        $st = $this->pdo->prepare('SELECT version FROM notes WHERE id = ? AND is_trashed = 0 LIMIT 1');
        $st->execute([$noteId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : null;
    }

    private function handleUpdate(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || !isset($this->noteSubscriptions[$note_id][$from->resourceId])) return;

        if (!$this->userCanEditNote((int) $from->user_id, $note_id)) return;

        $dbVersion = $this->fetchAuthoritativeNoteVersion($note_id);
        if ($dbVersion === null) return;

        $broadcastData = [
            'type'       => 'update',
            'note_id'    => $note_id,
            'user_name'  => $from->user_name,
            'title'      => $data['title'] ?? null,
            'content'    => $data['content'] ?? null,
            'version'    => $dbVersion,
            'sender_id'  => $from->resourceId,
            'timestamp'  => time(),
        ];

        foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
            if ($info['conn'] !== $from) {
                $info['conn']->send(json_encode($broadcastData));
            }
        }
    }

    /**
     * Broadcast danh sách người đang xem ghi chú
     */
    private function broadcastPresence(int $note_id) {
        if (!isset($this->noteSubscriptions[$note_id])) return;

        $users = array_values(array_map(
            function ($info) { return $info['user_name']; },
            $this->noteSubscriptions[$note_id]
        ));

        $payload = json_encode([
            'type'    => 'presence',
            'note_id' => $note_id,
            'users'   => $users,
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
        foreach (array_keys($this->noteSubscriptions) as $note_id) {
            $this->removeFromNote($conn, (int) $note_id);
        }
        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }

    // ==================== CÁC PHƯƠNG THỨC MỚI ====================
    /**
     * Broadcast color change to all viewers of the note
     */
    private function handleColorUpdate(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote((int) $from->user_id, $note_id)) return;

        $color = $data['color'] ?? '';

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'color_update',
                'note_id'   => $note_id,
                'color'     => $color,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }

    /**
     * Broadcast image added to all viewers
     */
    private function handleImageAdded(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote($from->user_id, $note_id)) return;

        $image_id = intval($data['image_id'] ?? 0);
        $file_path = $data['file_path'] ?? '';

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'image_added',
                'note_id'   => $note_id,
                'image_id'  => $image_id,
                'file_path' => $file_path,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }

    /**
     * Broadcast image deleted to all viewers
     */
    private function handleImageDeleted(ConnectionInterface $from, array $data) {
        $note_id = intval($data['note_id'] ?? 0);
        if (!$note_id || $from->user_id <= 0) return;
        if (!$this->userCanEditNote($from->user_id, $note_id)) return;

        $image_id = intval($data['image_id'] ?? 0);

        if (isset($this->noteSubscriptions[$note_id])) {
            $broadcastData = [
                'type'      => 'image_deleted',
                'note_id'   => $note_id,
                'image_id'  => $image_id,
                'user_name' => $from->user_name,
            ];
            foreach ($this->noteSubscriptions[$note_id] as $info) {
                if ($info['conn'] !== $from) {
                    $info['conn']->send(json_encode($broadcastData));
                }
            }
        }
    }
}