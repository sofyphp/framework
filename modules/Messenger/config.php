<?php

return [
    /*
     * Optional WebSocket URL for instant delivery. Leave empty to use polling
     * only (works everywhere, no infra). When set, the chat connects and uses
     * WS as a "new message" signal (a bump), still fetching message bodies over
     * HTTP — so no Redis bridge is needed, just a running ws:serve:
     *
     *   php sofy ws:serve --handler="Messenger\WebSocket\ChatHandler"
     *
     * Then proxy ws:// through nginx and point this at it, e.g.
     *   MESSENGER_WS_URL=wss://your-host/ws
     */
    'ws_url' => env('MESSENGER_WS_URL', ''),

    /*
     * Polling interval (ms) for live updates when WS isn't pushing.
     */
    'poll_interval' => (int) env('MESSENGER_POLL_INTERVAL', 4000),
];
