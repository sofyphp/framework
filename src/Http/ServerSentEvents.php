<?php

declare(strict_types=1);

namespace Sofy\Http;

/**
 * Server-Sent Events (SSE) helper.
 *
 * Usage in a controller:
 *   public function stream(): void
 *   {
 *       ServerSentEvents::start();
 *
 *       while (true) {
 *           ServerSentEvents::send(json_encode(['time' => time()]));
 *           sleep(1);
 *
 *           if (connection_aborted()) break;
 *       }
 *   }
 *
 * Client side (JS):
 *   const es = new EventSource('/stream');
 *   es.onmessage = e => console.log(JSON.parse(e.data));
 *   es.addEventListener('ping', e => console.log('ping'));
 */
class ServerSentEvents
{
    private static int $id = 0;

    /**
     * Send headers and disable output buffering.
     * Call this once before any SSE sends.
     */
    public static function start(): void
    {
        if (headers_sent()) return;

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // disable nginx buffering

        // Disable PHP output buffering
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        set_time_limit(0);
        ignore_user_abort(false);
    }

    /**
     * Send a data event.
     *
     * @param string      $data    Payload (plain text or JSON string)
     * @param string|null $event   Optional event name (client: es.addEventListener('event', ...))
     * @param int|null    $id      Optional message ID for reconnection
     * @param int|null    $retry   Reconnection delay in milliseconds
     */
    public static function send(
        string  $data,
        ?string $event = null,
        ?int    $id    = null,
        ?int    $retry = null,
    ): void {
        $id ??= ++self::$id;

        $output = '';

        if ($retry !== null) {
            $output .= "retry: $retry\n";
        }

        if ($event !== null) {
            $output .= "event: $event\n";
        }

        $output .= "id: $id\n";

        foreach (explode("\n", $data) as $line) {
            $output .= "data: $line\n";
        }

        $output .= "\n";

        echo $output;

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /** Send a ping comment to keep the connection alive. */
    public static function ping(): void
    {
        echo ": ping\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /** Check if the client disconnected. */
    public static function isConnected(): bool
    {
        return !connection_aborted();
    }
}
