<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeWebSocketCommand extends Command
{
    protected string $signature    = 'make:websocket {name : Handler class name (e.g. Chat)}';
    protected string $description  = 'Create a new WebSocket handler';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $name = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $name);

        $parts     = explode(DIRECTORY_SEPARATOR, $name);
        $className = array_pop($parts);
        $subNs     = implode('\\', $parts);

        $namespace = 'App\\WebSocket' . ($subNs ? '\\' . $subNs : '');
        $dir       = function_exists('base_path')
            ? base_path('app/WebSocket' . ($subNs ? '/' . str_replace('\\', '/', $subNs) : ''))
            : 'app/WebSocket';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/' . $className . '.php';

        if (file_exists($file)) {
            $this->error("Handler [$className] already exists.");
            return 1;
        }

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace $namespace;

        use Sofy\WebSocket\WebSocketConnection;
        use Sofy\WebSocket\WebSocketHandler;

        class $className extends WebSocketHandler
        {
            public function onOpen(WebSocketConnection \$conn): void
            {
                // Called when a client connects
            }

            public function onMessage(WebSocketConnection \$conn, string \$message): void
            {
                // Called when a text message arrives
                \$conn->send("Echo: \$message");
            }

            public function onClose(WebSocketConnection \$conn): void
            {
                // Called when a client disconnects
            }
        }
        PHP;

        // Dedent: remove leading spaces added by heredoc indentation
        $stub = preg_replace('/^        /m', '', $stub);

        file_put_contents($file, $stub);

        $this->success("WebSocket handler [$className] created at app/WebSocket/$className.php");
        $this->line("Run: php sofy ws:serve --handler=$namespace\\$className");

        return 0;
    }
}
