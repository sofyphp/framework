<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Cache\Cache;
use Sofy\Http\Request;
use Sofy\Http\Response;

readonly class ThrottleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxAttempts  = 60,
        private readonly int $decaySeconds = 60,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $key      = 'throttle:' . md5($this->clientIp() . '|' . $request->path());
        $attempts = Cache::increment($key);

        if ($attempts === 1) {
            // Установить TTL только при первом обращении
            Cache::set($key, 1, $this->decaySeconds);
        }

        if ($attempts > $this->maxAttempts) {
            return new Response('Too Many Requests.', 429, [
                'Retry-After'           => (string) $this->decaySeconds,
                'X-RateLimit-Limit'     => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $response = $next($request);
        $remaining = max(0, $this->maxAttempts - $attempts);

        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function clientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
