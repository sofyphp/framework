<?php

declare(strict_types=1);

use Sofy\Cache\Cache;
use Sofy\Core\Application;
use Sofy\Events\Dispatcher;
use Sofy\Http\Client\Http;
use Sofy\Http\Client\HttpClient;
use Sofy\Http\Response;
use Sofy\Http\Session;
use Sofy\Log\Log;
use Sofy\Security\Crypt;
use Sofy\Security\Hash;
use Sofy\Support\Collection;
use Sofy\Support\Str;
use Sofy\Support\Stringable;

if (!function_exists('app')) {
    function app(string $abstract = Application::class): mixed
    {
        return Application::getInstance()->get($abstract);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value === false || $value === null) ? $default : $value;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): Response
    {
        return Response::view($template, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('response')) {
    function response(string $content = '', int $status = 200, array $headers = []): Response
    {
        return new Response($content, $status, $headers);
    }
}

if (!function_exists('json_response')) {
    /**
     * @throws JsonException
     */
    function json_response(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}

if (!function_exists('http')) {
    function http(): HttpClient
    {
        return Http::new();
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return Application::getInstance()->basePath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return Application::getInstance()->storagePath($path);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('collect')) {
    function collect(array $items = []): Collection
    {
        return Collection::make($items);
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        static $session = null;
        if ($session === null) {
            $session = new Session();
            $session->start();
        }
        return $key === null ? $session : $session->get($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        /** @var Session $s */
        $s = session(); // reuses the singleton from session() — no double session_start()
        return $s->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('logger')) {
    function logger(string $message, array $context = [], string $level = 'debug'): void
    {
        Log::{$level}($message, $context);
    }
}

if (!function_exists('cache')) {
    function cache(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return Cache::class;
        return Cache::get($key, $default);
    }
}

if (!function_exists('str')) {
    function str(string $value = ''): Stringable
    {
        return Str::of($value);
    }
}

if (!function_exists('event')) {
    function event(string|object $event, array $payload = []): array
    {
        return Dispatcher::getInstance()->dispatch($event, $payload);
    }
}

if (!function_exists('listen')) {
    function listen(string $event, callable $listener): void
    {
        Dispatcher::getInstance()->listen($event, $listener);
    }
}

if (!function_exists('bcrypt')) {
    function bcrypt(string $value): string
    {
        return Hash::make($value);
    }
}

if (!function_exists('encrypt')) {
    function encrypt(mixed $value): string
    {
        return Crypt::encrypt($value);
    }
}

if (!function_exists('decrypt')) {
    function decrypt(string $payload): mixed
    {
        return Crypt::decrypt($payload);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new \Sofy\Http\HttpException($status, $message);
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('abort_if')) {
    function abort_if(bool $condition, int $status, string $message = ''): void
    {
        if ($condition) {
            abort($status, $message);
        }
    }
}

if (!function_exists('abort_unless')) {
    function abort_unless(bool $condition, int $status, string $message = ''): void
    {
        if (!$condition) {
            abort($status, $message);
        }
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = null): mixed
    {
        session(); // ensure session started via shared singleton
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        return Application::getInstance()->router()->route($name, $params);
    }
}

if (!function_exists('redis')) {
    function redis(): \Sofy\Redis\RedisClient
    {
        return \Sofy\Redis\RedisClient::getInstance();
    }
}

if (!function_exists('trans')) {
    function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Sofy\Support\Lang::trans($key, $replace, $locale);
    }
}

if (!function_exists('__')) {
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \Sofy\Support\Lang::trans($key, $replace, $locale);
    }
}

if (!function_exists('can')) {
    function can(string $ability, mixed $arguments = null): bool
    {
        return \Sofy\Auth\Gate::allows($ability, $arguments);
    }
}

if (!function_exists('broadcast')) {
    function broadcast(string $channel, string $event, array $data = []): void
    {
        \Sofy\Broadcasting\Broadcaster::broadcast($channel, $event, $data);
    }
}

if (!function_exists('signed_url')) {
    function signed_url(string $route, array $params = []): string
    {
        return \Sofy\Support\Url::signedRoute($route, $params);
    }
}

if (!function_exists('temporary_signed_url')) {
    function temporary_signed_url(string $route, array $params = [], int $minutes = 30): string
    {
        return \Sofy\Support\Url::temporarySignedRoute($route, $params, $minutes);
    }
}
