<?php

declare(strict_types=1);

namespace Sofy\Http\Middleware;

use Sofy\Http\Request;
use Sofy\Http\Response;

/**
 * Returns 503 Service Unavailable when the app is in maintenance mode.
 *
 * Maintenance mode is activated by creating a .maintenance file in the
 * project root (done by `php sofy down`).
 *
 * Add to the global middleware stack in bootstrap/app.php:
 *   $app->middleware([MaintenanceMiddleware::class, ...]);
 */
class MaintenanceMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $file = $this->maintenanceFile();

        if (!file_exists($file)) {
            return $next($request);
        }

        $data    = @json_decode((string) file_get_contents($file), true) ?? [];
        $message = $data['message'] ?? 'We\'ll be back shortly. Maintenance in progress.';
        $retry   = (int) ($data['retry'] ?? 60);

        // Allow configured bypass IPs
        $allowed = $data['allow'] ?? [];
        if ($allowed && in_array($request->ip(), (array) $allowed, true)) {
            return $next($request);
        }

        $response = new Response($message, 503, [
            'Content-Type'    => 'text/plain; charset=UTF-8',
            'Retry-After'     => (string) $retry,
        ]);

        return $response;
    }

    private function maintenanceFile(): string
    {
        return function_exists('base_path')
            ? base_path('.maintenance')
            : dirname(__DIR__, 4) . '/.maintenance';
    }
}
