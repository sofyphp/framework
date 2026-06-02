<?php

declare(strict_types=1);

namespace Sofy\Module\Marketplace;

/**
 * Outcome of a marketplace install/uninstall. The Installer never throws —
 * controllers and CLI commands read $ok + $message + $log and render
 * them in their own UI without try/catch.
 */
final class InstallResult
{
    /** @param list<string> $log */
    private function __construct(
        public readonly bool   $ok,
        public readonly string $message,
        public readonly array  $log,
    ) {}

    /** @param list<string> $log */
    public static function success(string $message, array $log = []): self
    {
        return new self(true, $message, $log);
    }

    /** @param list<string> $log */
    public static function failure(string $message, array $log = []): self
    {
        return new self(false, $message, $log);
    }
}
