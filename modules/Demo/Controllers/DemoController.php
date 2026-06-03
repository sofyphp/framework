<?php

declare(strict_types=1);

namespace Demo\Controllers;

use Sofy\Http\Response;

/**
 * Demo module endpoints. Controller actions (not closures) so the module
 * doesn't block `php sofy route:cache` for the whole app — closures can't be
 * serialized, and one closure route disables the entire route cache.
 */
class DemoController
{
    public function greeting(): string
    {
        return (string) config('demo.greeting', 'Hello!');
    }

    public function info(): Response
    {
        return view('demo::info', [
            'greeting' => config('demo.greeting'),
            'version'  => config('demo.version'),
        ]);
    }

    /** @return array<string, mixed> */
    public function api(): array
    {
        return [
            'module'   => 'demo',
            'greeting' => config('demo.greeting'),
            'version'  => config('demo.version'),
        ];
    }
}
