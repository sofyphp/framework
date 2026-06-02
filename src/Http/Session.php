<?php

declare(strict_types=1);

namespace Sofy\Http;

class Session
{
    private bool $started = false;

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $this->configureDriver();
        $this->configureCookieParams();

        session_start();
        $this->started = true;
    }

    /**
     * Force HttpOnly / SameSite=Lax / Secure-when-https on the session
     * cookie so a fresh install doesn't inherit whatever the host's
     * php.ini happened to ship with. Must be called before session_start().
     *
     * - HttpOnly      blocks document.cookie XSS theft
     * - SameSite=Lax  blocks classic CSRF cookie attachment from a third
     *                 party without breaking top-level navigation
     * - Secure        only set on HTTPS — sending it on plain HTTP would
     *                 make the cookie undeliverable
     */
    private function configureCookieParams(): void
    {
        $ttl    = function_exists('config') ? (int) config('session.lifetime', 120) * 60 : 7200;
        $cookie = function_exists('config') ? (string) config('session.cookie', 'sofy_session') : 'sofy_session';

        session_name($cookie);

        $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '')) === '443'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        session_set_cookie_params([
            'lifetime' => $ttl,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Install a custom session handler when SESSION_DRIVER=redis.
     * Called before session_start() — must run before any headers are sent.
     */
    private function configureDriver(): void
    {
        $driver = function_exists('config')
            ? (string) config('session.driver', 'file')
            : (string) ($_ENV['SESSION_DRIVER'] ?? 'file');

        if ($driver !== 'redis') {
            return;
        }

        $ttl    = function_exists('config') ? (int) config('session.lifetime', 120) * 60 : 7200;
        $prefix = function_exists('config') ? (string) config('session.prefix', 'sofy_sess:') : 'sofy_sess:';

        // Use a dedicated Redis DB for sessions if configured
        $redisDb = function_exists('config') ? config('session.redis_db') : null;

        $redisCfg = function_exists('config') ? (array) config('cache.redis', []) : [];
        if ($redisDb !== null) {
            $redisCfg['database'] = (int) $redisDb;
        }

        $client  = \Sofy\Redis\RedisClient::getInstance();
        $handler = new RedisSessionHandler($client, $ttl, $prefix);

        session_set_save_handler($handler, true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Запись в flash — доступна один раз при следующем запросе. */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    /** Чтение и удаление flash-значения. */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /** Перемещает flash из «новых» в «текущие» (вызывать в начале запроса). */
    public function ageFlash(): void
    {
        $_SESSION['_flash']     = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    public function flush(): void
    {
        $_SESSION = [];
    }

    public function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
    }

    /** CSRF-токен сессии. */
    public function token(): string
    {
        if (!$this->has('_token')) {
            $this->set('_token', bin2hex(random_bytes(32)));
        }
        return (string) $this->get('_token');
    }
}
