<?php

declare(strict_types=1);

namespace Sofy\Feature;

/**
 * Fluent context for per-user flag checks.
 *
 *   Flag::for($user)->enabled('beta-charts')
 */
readonly class FlagContext
{
    public function __construct(private readonly mixed $user) {}

    public function enabled(string $name): bool
    {
        return Flag::enabled($name, $this->user);
    }
}
