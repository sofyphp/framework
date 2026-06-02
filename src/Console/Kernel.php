<?php

declare(strict_types=1);

namespace Sofy\Console;

use Sofy\Console\Commands\ListCommand;
use Sofy\Console\Commands\MakeControllerCommand;
use Sofy\Console\Commands\MakeJobCommand;
use Sofy\Console\Commands\MakeMigrationCommand;
use Sofy\Console\Commands\MakeModelCommand;
use Sofy\Console\Commands\MigrateCommand;
use Sofy\Console\Commands\QueueFlushCommand;
use Sofy\Console\Commands\QueueRetryCommand;
use Sofy\Console\Commands\QueueTableCommand;
use Sofy\Console\Commands\QueueWorkCommand;
use Sofy\Console\Commands\ScheduleListCommand;
use Sofy\Console\Commands\CacheClearCommand;
use Sofy\Console\Commands\DbSeedCommand;
use Sofy\Console\Commands\FullInstallCommand;
use Sofy\Console\Commands\KeyGenerateCommand;
use Sofy\Console\Commands\MakeRequestCommand;
use Sofy\Console\Commands\MakeResourceCommand;
use Sofy\Console\Commands\MakeSeederCommand;
use Sofy\Console\Commands\MakeEventCommand;
use Sofy\Console\Commands\MakeFlagCommand;
use Sofy\Console\Commands\MakeMailCommand;
use Sofy\Console\Commands\MakeMiddlewareCommand;
use Sofy\Console\Commands\MakeModuleCommand;
use Sofy\Console\Commands\MarketplaceInstallCommand;
use Sofy\Console\Commands\MarketplaceListCommand;
use Sofy\Console\Commands\MarketplaceUninstallCommand;
use Sofy\Console\Commands\ModuleInstallCommand;
use Sofy\Console\Commands\MakeWebSocketCommand;
use Sofy\Console\Commands\WsServeCommand;
use Sofy\Console\Commands\ReplCommand;
use Sofy\Console\Commands\RouteListCommand;
use Sofy\Console\Commands\ScheduleRunCommand;
use Sofy\Console\Commands\DownCommand;
use Sofy\Console\Commands\UpCommand;
use Sofy\Console\Commands\ConfigCacheCommand;
use Sofy\Console\Commands\ConfigClearCommand;
use Sofy\Console\Commands\ViewClearCommand;
use Sofy\Console\Commands\RouteCacheCommand;
use Sofy\Console\Commands\RouteClearCommand;
use Sofy\Console\Commands\MakePolicyCommand;
use Sofy\Console\Commands\MakeNotificationCommand;
use Sofy\Console\Commands\MakeTestCommand;
use Sofy\Console\Commands\MakeFactoryCommand;
use Sofy\Console\Commands\MakeObserverCommand;
use Sofy\Console\Commands\ServeCommand;
use Sofy\Console\Commands\UpdateCommand;
use Sofy\Console\Commands\AdminCreateCommand;

class Kernel
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    public function __construct()
    {
        $this->register(ServeCommand::class);
        $this->register(FullInstallCommand::class);
        $this->register(UpdateCommand::class);
        $this->register(AdminCreateCommand::class);
        $this->register(ListCommand::class);
        $this->register(MakeControllerCommand::class);
        $this->register(MakeJobCommand::class);
        $this->register(MakeMigrationCommand::class);
        $this->register(MakeModelCommand::class);
        $this->register(MigrateCommand::class);
        $this->register(QueueFlushCommand::class);
        $this->register(QueueRetryCommand::class);
        $this->register(QueueTableCommand::class);
        $this->register(QueueWorkCommand::class);
        $this->register(ScheduleListCommand::class);
        $this->register(ScheduleRunCommand::class);
        $this->register(KeyGenerateCommand::class);
        $this->register(CacheClearCommand::class);
        $this->register(DbSeedCommand::class);
        $this->register(MakeSeederCommand::class);
        $this->register(MakeRequestCommand::class);
        $this->register(MakeResourceCommand::class);
        $this->register(RouteListCommand::class);
        $this->register(MakeMiddlewareCommand::class);
        $this->register(MakeEventCommand::class);
        $this->register(MakeMailCommand::class);
        $this->register(MakeFlagCommand::class);
        $this->register(MakeModuleCommand::class);
        $this->register(ModuleInstallCommand::class);
        $this->register(MarketplaceListCommand::class);
        $this->register(MarketplaceInstallCommand::class);
        $this->register(MarketplaceUninstallCommand::class);
        $this->register(WsServeCommand::class);
        $this->register(MakeWebSocketCommand::class);
        $this->register(ReplCommand::class);
        $this->register(DownCommand::class);
        $this->register(UpCommand::class);
        $this->register(ConfigCacheCommand::class);
        $this->register(ConfigClearCommand::class);
        $this->register(ViewClearCommand::class);
        $this->register(RouteCacheCommand::class);
        $this->register(RouteClearCommand::class);
        $this->register(MakePolicyCommand::class);
        $this->register(MakeNotificationCommand::class);
        $this->register(MakeTestCommand::class);
        $this->register(MakeFactoryCommand::class);
        $this->register(MakeObserverCommand::class);
    }

    /** Регистрирует класс команды. */
    public function register(string $commandClass): static
    {
        /** @var Command $instance */
        $instance = new $commandClass();
        $name     = $this->parseName($instance->getSignature());

        $this->commands[$name] = $commandClass;
        return $this;
    }

    /** Запускает CLI по $argv. Возвращает код выхода. */
    public function run(array $argv): int
    {
        $argv = array_values(array_slice($argv, 1));
        $name = array_shift($argv) ?? 'list';

        if ($name === '--help' || $name === '-h') {
            $name = 'list';
        }

        if (!isset($this->commands[$name])) {
            $output = new Output();
            $output->error("Command [$name] not found.");
            $output->line("Run 'php sofy list' to see available commands.");
            return 1;
        }

        /** @var Command $command */
        $command = new $this->commands[$name]();

        [$argDefs, $optDefs] = $this->parseSignature($command->getSignature());
        [$arguments, $options] = $this->parseArgv($argv, $argDefs, $optDefs);

        $command->setArguments($arguments);
        $command->setOptions($options);

        if ($command instanceof ListCommand) {
            $command->setCommands($this->commands);
        }

        return $command->handle();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function parseName(string $signature): string
    {
        return trim(explode(' ', $signature, 2)[0]);
    }

    /**
     * Разбирает сигнатуру команды на определения аргументов и опций.
     *
     * Форматы:
     *   {name}         — обязательный аргумент
     *   {name?}        — необязательный аргумент
     *   {--flag}       — булева опция
     *   {--option=}    — опция со значением
     *   {--option=val} — опция со значением по умолчанию
     *   {... : desc}   — описание после " : " игнорируется
     */
    private function parseSignature(string $signature): array
    {
        preg_match_all('/\{([^}]+)}/', $signature, $matches);

        $argDefs = [];
        $optDefs = [];

        foreach ($matches[1] as $token) {
            $token = trim(preg_split('/\s*:\s*/', trim($token), 2)[0]);

            if (str_starts_with($token, '--')) {
                $inner    = substr($token, 2);
                $hasValue = str_contains($inner, '=');
                $default  = null;

                if ($hasValue) {
                    [$optName, $default] = array_pad(explode('=', $inner, 2), 2, null);
                } else {
                    $optName = $inner;
                }

                $optDefs[] = ['name' => $optName, 'hasValue' => $hasValue, 'default' => $default];
            } else {
                $optional  = str_ends_with($token, '?');
                $argDefs[] = ['name' => rtrim($token, '?'), 'optional' => $optional];
            }
        }

        return [$argDefs, $optDefs];
    }

    /**
     * Разбирает фактический $argv на именованные аргументы и опции.
     */
    private function parseArgv(array $argv, array $argDefs, array $optDefs): array
    {
        $arguments  = [];
        $options    = [];
        $positional = [];

        // Заполняем опции значениями по умолчанию
        foreach ($optDefs as $def) {
            $options[$def['name']] = $def['hasValue'] ? $def['default'] : false;
        }

        $i = 0;
        while ($i < count($argv)) {
            $token = $argv[$i];

            if (str_starts_with($token, '--')) {
                $inner = substr($token, 2);

                if (str_contains($inner, '=')) {
                    [$key, $val] = explode('=', $inner, 2);
                } else {
                    $key     = $inner;
                    $val     = true;
                    $optDef  = array_values(array_filter($optDefs, fn($d) => $d['name'] === $key));

                    if (!empty($optDef) && $optDef[0]['hasValue']
                        && isset($argv[$i + 1])
                        && !str_starts_with($argv[$i + 1], '--')
                    ) {
                        $i++;
                        $val = $argv[$i];
                    }
                }

                $options[$key] = $val;
            } else {
                $positional[] = $token;
            }

            $i++;
        }

        foreach ($argDefs as $idx => $def) {
            $arguments[$def['name']] = $positional[$idx] ?? null;
        }

        return [$arguments, $options];
    }
}
