<?php

declare(strict_types=1);

namespace Main\Controllers\Api;

use Sofy\Http\Response;

/**
 * Health-check endpoint. A controller (not a closure) so the API routes stay
 * cacheable via `php sofy route:cache`.
 */
class PingController
{
    public function index(): Response
    {
        return json_response(['status' => 'ok', 'time' => now()]);
    }
}
