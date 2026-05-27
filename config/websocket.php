<?php

return [
    /*
     * Default WebSocket handler FQCN.
     * Used when ws:serve is run without --handler option.
     */
    'handler' => env('WS_HANDLER', ''),

    /*
     * Address and port the WebSocket server binds to.
     * Override with --host / --port on the command line.
     */
    'host' => env('WS_HOST', '0.0.0.0'),
    'port' => (int) env('WS_PORT', 8080),
];
