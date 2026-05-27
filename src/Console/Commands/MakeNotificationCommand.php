<?php

declare(strict_types=1);

namespace Sofy\Console\Commands;

use Sofy\Console\Command;

class MakeNotificationCommand extends Command
{
    protected string $signature   = 'make:notification {name : Notification class name}';
    protected string $description = 'Create a new notification class';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $dir  = function_exists('base_path') ? base_path('app/Notifications') : 'app/Notifications';
        $file = $dir . '/' . $name . '.php';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($file)) {
            $this->error("Notification [$name] already exists.");
            return 1;
        }

        $stub = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\Notifications;

        use Sofy\Notification\Notification;

        class $name extends Notification
        {
            public function via(mixed \$notifiable): array
            {
                return ['mail', 'database'];
            }

            public function toMail(mixed \$notifiable): array
            {
                return [
                    'subject' => '$name',
                    'body'    => 'Notification body.',
                ];
            }

            public function toDatabase(mixed \$notifiable): array
            {
                return [
                    // Additional data to store
                ];
            }
        }
        PHP;

        $stub = preg_replace('/^        /m', '', $stub);
        file_put_contents($file, $stub);

        $this->success("Notification [$name] created at app/Notifications/$name.php");

        return 0;
    }
}
