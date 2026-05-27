<?php

declare(strict_types=1);

namespace Sofy\WebSocket;

/**
 * Represents a single connected WebSocket client.
 *
 * Handles per-connection state: read buffer, fragmented frame accumulation,
 * room membership, and arbitrary user-set attributes.
 * Frame writing is also delegated here so the server loop stays clean.
 */
class WebSocketConnection
{
    public readonly string $id;
    public bool $handshaked = false;

    /** @var string[] */
    private array $rooms = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    private string $readBuffer    = '';
    private string $fragmentBuf   = '';
    private int    $fragmentOpcode = 0;

    /** @param resource $socket */
    public function __construct(private readonly mixed $socket)
    {
        $this->id = bin2hex(random_bytes(8));
    }

    /** @return resource */
    public function getSocket(): mixed
    {
        return $this->socket;
    }

    // ── Sending ───────────────────────────────────────────────────────────────

    public function send(string $message): void
    {
        $this->writeFrame($message, 0x1);
    }

    public function sendBinary(string $data): void
    {
        $this->writeFrame($data, 0x2);
    }

    public function sendJson(mixed $data): void
    {
        $this->send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function ping(): void
    {
        $this->writeFrame('', 0x9);
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        $payload = pack('n', $code) . $reason;
        $this->writeFrame($payload, 0x8);
    }

    // ── Rooms ─────────────────────────────────────────────────────────────────

    public function join(string $room): self
    {
        if (!in_array($room, $this->rooms, true)) {
            $this->rooms[] = $room;
        }
        return $this;
    }

    public function leave(string $room): self
    {
        $this->rooms = array_values(array_filter($this->rooms, fn($r) => $r !== $room));
        return $this;
    }

    public function getRooms(): array
    {
        return $this->rooms;
    }

    public function inRoom(string $room): bool
    {
        return in_array($room, $this->rooms, true);
    }

    // ── Attributes ────────────────────────────────────────────────────────────

    public function set(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    // ── Buffer management (used by WebSocketServer) ───────────────────────────

    public function appendBuffer(string $data): void
    {
        $this->readBuffer .= $data;
    }

    public function getBuffer(): string
    {
        return $this->readBuffer;
    }

    public function consumeBuffer(int $bytes): void
    {
        $this->readBuffer = substr($this->readBuffer, $bytes);
    }

    public function clearBuffer(): void
    {
        $this->readBuffer = '';
    }

    // ── Fragmented frame accumulation (used by WebSocketServer) ──────────────

    public function appendFragment(string $payload, int $opcode): void
    {
        if ($opcode !== 0x0) {
            $this->fragmentOpcode = $opcode;
        }
        $this->fragmentBuf .= $payload;
    }

    /** Returns ['opcode' => int, 'payload' => string] and clears the buffer. */
    public function flushFragment(): array
    {
        $result = ['opcode' => $this->fragmentOpcode, 'payload' => $this->fragmentBuf];
        $this->fragmentBuf    = '';
        $this->fragmentOpcode = 0;
        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function writeFrame(string $payload, int $opcode): void
    {
        $len    = strlen($payload);
        $header = chr(0x80 | $opcode); // FIN=1 + opcode

        if ($len < 126) {
            $header .= chr($len);
        } elseif ($len < 65536) {
            $header .= chr(126) . pack('n', $len);
        } else {
            $header .= chr(127) . pack('J', $len);
        }

        @fwrite($this->socket, $header . $payload);
    }
}
