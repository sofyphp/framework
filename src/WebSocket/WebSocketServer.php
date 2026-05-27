<?php

declare(strict_types=1);

namespace Sofy\WebSocket;

use RuntimeException;
use Throwable;

/**
 * Pure-PHP WebSocket server (RFC 6455).
 *
 * Uses stream_socket_server + stream_select — no ext-sockets required.
 * Supports:
 *   - Text / binary frames
 *   - Fragmented messages (multi-frame)
 *   - Ping / Pong (auto-reply)
 *   - Close handshake
 *   - Rooms (logical broadcast groups)
 *
 * Usage:
 *   $server = new WebSocketServer(new ChatHandler(), '0.0.0.0', 8080);
 *   $server->run();   // blocks — run in a dedicated process
 */
class WebSocketServer
{
    /** @var array<string, WebSocketConnection>  id → connection */
    private array $connections = [];

    /** @var array<int, WebSocketConnection>  (int)socket → connection */
    private array $bySocket = [];

    /** @var resource|false */
    private mixed $serverSocket = false;

    private bool $running = false;

    public function __construct(
        private readonly WebSocketHandler $handler,
        private readonly string           $host    = '0.0.0.0',
        private readonly int              $port    = 8080,
        private readonly int              $selectTimeout = 1,
    ) {
        $handler->setServer($this);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function run(): void
    {
        $addr = "tcp://$this->host:$this->port";
        $this->serverSocket = stream_socket_server(
            $addr, $errno, $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );

        if ($this->serverSocket === false) {
            throw new RuntimeException(
                "WebSocket: cannot bind to $this->host:$this->port — $errstr ($errno)"
            );
        }

        stream_set_blocking($this->serverSocket, false);
        $this->running = true;

        echo "WebSocket server listening on ws://$this->host:$this->port" . PHP_EOL;

        while ($this->running) {
            $read   = [$this->serverSocket];
            foreach ($this->connections as $conn) {
                $read[] = $conn->getSocket();
            }
            $write  = null;
            $except = null;

            if (@stream_select($read, $write, $except, $this->selectTimeout) === false) {
                continue;
            }

            foreach ($read as $stream) {
                if ($stream === $this->serverSocket) {
                    $this->acceptConnection();
                } else {
                    $this->handleStream($stream);
                }
            }
        }

        foreach ($this->connections as $conn) {
            $conn->close(1001, 'Server shutting down');
            @fclose($conn->getSocket());
        }

        fclose($this->serverSocket);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    // ── Broadcast helpers ─────────────────────────────────────────────────────

    /** Send a text message to every connected client. */
    public function broadcast(string $message, ?string $exceptId = null): void
    {
        foreach ($this->connections as $conn) {
            if ($exceptId === null || $conn->id !== $exceptId) {
                $conn->send($message);
            }
        }
    }

    /** Send a text message to all clients in a room. */
    public function to(string $room, string $message, ?string $exceptId = null): void
    {
        foreach ($this->connections as $conn) {
            if ($conn->inRoom($room) && ($exceptId === null || $conn->id !== $exceptId)) {
                $conn->send($message);
            }
        }
    }

    /** Send JSON to all clients in a room. */
    public function toJson(string $room, mixed $data, ?string $exceptId = null): void
    {
        $json = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->to($room, $json, $exceptId);
    }

    /** @return WebSocketConnection[] */
    public function connections(): array
    {
        return $this->connections;
    }

    public function connection(string $id): ?WebSocketConnection
    {
        return $this->connections[$id] ?? null;
    }

    public function connectionCount(): int
    {
        return count($this->connections);
    }

    // ── Accept ────────────────────────────────────────────────────────────────

    private function acceptConnection(): void
    {
        $socket = @stream_socket_accept($this->serverSocket, 0);
        if ($socket === false) {
            return;
        }
        stream_set_blocking($socket, false);

        $conn = new WebSocketConnection($socket);
        $this->connections[$conn->id]  = $conn;
        $this->bySocket[(int) $socket] = $conn;
    }

    // ── Read / dispatch ───────────────────────────────────────────────────────

    private function handleStream(mixed $stream): void
    {
        $conn = $this->bySocket[(int) $stream] ?? null;
        if ($conn === null) {
            return;
        }

        $data = @fread($stream, 65536);

        if ($data === false || $data === '') {
            // EOF — client disconnected
            $this->closeConnection($conn, sendClose: false);
            return;
        }

        $conn->appendBuffer($data);

        if (!$conn->handshaked) {
            $this->doHandshake($conn);
        } else {
            $this->processFrames($conn);
        }
    }

    // ── HTTP → WebSocket handshake ────────────────────────────────────────────

    private function doHandshake(WebSocketConnection $conn): void
    {
        $buf = $conn->getBuffer();

        // Wait until we have a complete HTTP request
        if (!str_contains($buf, "\r\n\r\n")) {
            return;
        }

        if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $buf, $m)) {
            // Not a valid WebSocket upgrade — reject
            fwrite($conn->getSocket(), "HTTP/1.1 400 Bad Request\r\n\r\n");
            $this->closeConnection($conn, sendClose: false);
            return;
        }

        $accept = base64_encode(
            sha1(trim($m[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)
        );

        $response = implode("\r\n", [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Accept: $accept",
            '', '',
        ]);

        fwrite($conn->getSocket(), $response);

        // Keep bytes that arrived after the HTTP headers (first frame data)
        $headerEnd = strpos($buf, "\r\n\r\n");
        $leftover  = substr($buf, $headerEnd + 4);
        $conn->clearBuffer();

        // Store request path for handler use
        if (preg_match('#^GET ([^\s]+)#', $buf, $pathMatch)) {
            $conn->set('path', $pathMatch[1]);
        }

        $conn->handshaked = true;

        try {
            $this->handler->onOpen($conn);
        } catch (Throwable $e) {
            $this->handler->onError($conn, $e);
        }

        if ($leftover !== '') {
            $conn->appendBuffer($leftover);
            $this->processFrames($conn);
        }
    }

    // ── Frame processing ──────────────────────────────────────────────────────

    private function processFrames(WebSocketConnection $conn): void
    {
        while (true) {
            $frame = $this->decodeFrame($conn->getBuffer());
            if ($frame === null) {
                break; // incomplete frame — wait for more data
            }

            $conn->consumeBuffer($frame['totalLength']);

            $opcode  = $frame['opcode'];
            $payload = $frame['payload'];
            $fin     = $frame['fin'];

            // Control frames (never fragmented, max 125 bytes)
            if ($opcode === 0x8) {
                // Close
                $code   = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : 1000;
                $reason = strlen($payload) > 2 ? substr($payload, 2) : '';
                $conn->close($code, $reason);
                $this->closeConnection($conn, sendClose: false);
                return;
            }

            if ($opcode === 0x9) {
                // Ping → auto-reply pong
                $this->sendPong($conn, $payload);
                try {
                    $this->handler->onPing($conn);
                } catch (Throwable) {}
                continue;
            }

            if ($opcode === 0xA) {
                // Pong — nothing to do
                continue;
            }

            // Data frames: text (0x1), binary (0x2), continuation (0x0)
            $conn->appendFragment($payload, $opcode);

            if ($fin) {
                $msg = $conn->flushFragment();
                try {
                    if ($msg['opcode'] === 0x1) {
                        $this->handler->onMessage($conn, $msg['payload']);
                    } elseif ($msg['opcode'] === 0x2) {
                        $this->handler->onBinary($conn, $msg['payload']);
                    }
                } catch (Throwable $e) {
                    $this->handler->onError($conn, $e);
                }
            }
        }
    }

    // ── Frame codec ───────────────────────────────────────────────────────────

    /**
     * Decode one WebSocket frame from the front of $data.
     * Returns null when $data does not contain a complete frame yet.
     *
     * @return array{fin:bool,opcode:int,payload:string,totalLength:int}|null
     */
    private function decodeFrame(string $data): ?array
    {
        $dataLen = strlen($data);
        if ($dataLen < 2) {
            return null;
        }

        $b0     = ord($data[0]);
        $b1     = ord($data[1]);
        $fin    = ($b0 & 0x80) !== 0;
        $opcode = $b0 & 0x0F;
        $masked = ($b1 & 0x80) !== 0;
        $len    = $b1 & 0x7F;
        $offset = 2;

        if ($len === 126) {
            if ($dataLen < 4) return null;
            $len    = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($len === 127) {
            if ($dataLen < 10) return null;
            $len    = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        $maskBytes = $masked ? 4 : 0;
        if ($dataLen < $offset + $maskBytes + $len) {
            return null; // frame not yet complete in buffer
        }

        if ($masked) {
            $mask    = substr($data, $offset, 4);
            $offset += 4;
            $raw     = substr($data, $offset, $len);
            $payload = '';
            for ($i = 0; $i < $len; $i++) {
                $payload .= chr(ord($raw[$i]) ^ ord($mask[$i & 3]));
            }
        } else {
            $payload = substr($data, $offset, $len);
        }

        return [
            'fin'         => $fin,
            'opcode'      => $opcode,
            'payload'     => $payload,
            'totalLength' => $offset + $len, // $offset already accounts for mask bytes when masked
        ];
    }

    private function sendPong(WebSocketConnection $conn, string $payload): void
    {
        @fwrite($conn->getSocket(), $this->buildFrame($payload, 0xA));
    }

    private static function buildFrame(string $payload, int $opcode): string
    {
        $len    = strlen($payload);
        $header = chr(0x80 | $opcode);

        if ($len < 126) {
            $header .= chr($len);
        } elseif ($len < 65536) {
            $header .= chr(126) . pack('n', $len);
        } else {
            $header .= chr(127) . pack('J', $len);
        }

        return $header . $payload;
    }

    // ── Cleanup ───────────────────────────────────────────────────────────────

    private function closeConnection(WebSocketConnection $conn, bool $sendClose = true): void
    {
        if ($sendClose) {
            $conn->close();
        }

        try {
            $this->handler->onClose($conn);
        } catch (Throwable) {}

        unset(
            $this->connections[$conn->id],
            $this->bySocket[(int) $conn->getSocket()],
        );

        @fclose($conn->getSocket());
    }
}
