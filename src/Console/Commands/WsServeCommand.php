<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;
use Sofy\WebSocket\WebSocketServer;
use Sofy\WebSocket\WebSocketHandler;

class WsServeCommand extends Command
{
    protected string $signature = 'ws:serve'
        . ' {--host= : IP address to listen on (default: WS_HOST / 0.0.0.0)}'
        . ' {--port= : TCP port (default: WS_PORT / 8080)}'
        . ' {--handler= : FQCN of WebSocketHandler subclass}';

    protected string $description = 'Start the WebSocket server';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: (function_exists('config') ? config('websocket.host', '0.0.0.0') : '0.0.0.0'));
        $port = (int)    ($this->option('port') ?: (function_exists('config') ? config('websocket.port', 8080)    : 8080));
        $handlerClass = (string) ($this->option('handler') ?? '');

        if ($handlerClass === '') {
            $handlerClass = function_exists('config')
                ? (string) config('websocket.handler', '')
                : '';
        }

        if ($handlerClass === '' || !class_exists($handlerClass)) {
            $this->error(
                $handlerClass === ''
                    ? 'No handler specified. Use --handler=App\\WebSocket\\YourHandler'
                    : "Handler class [$handlerClass] not found."
            );
            return 1;
        }

        if (!is_subclass_of($handlerClass, WebSocketHandler::class)) {
            $this->error("[$handlerClass] must extend " . WebSocketHandler::class);
            return 1;
        }

        /** @var WebSocketHandler $handler */
        $handler = new $handlerClass();

        $this->info("Starting WebSocket server on ws://$host:$port");
        $this->line("Handler: $handlerClass");
        $this->line('Press Ctrl+C to stop.');
        $this->line();

        $server = new WebSocketServer($handler, $host, $port);

        $server->run();

        $this->line('WebSocket server stopped.');
        return 0;
    }
}
