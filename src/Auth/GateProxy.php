<?php

declare(strict_types=1);

namespace Sofy\Auth;

/** Scoped gate check for an explicit user (not Auth::user()). */
class GateProxy
{
    public function __construct(
        private readonly mixed $user,
        private readonly array $abilities,
        private readonly array $policies,
    ) {}

    public function allows(string $ability, mixed $arguments = null): bool
    {
        if ($this->user === null) return false;

        $model = is_object($arguments) ? $arguments::class : (is_string($arguments) ? $arguments : null);
        if ($model !== null && isset($this->policies[$model])) {
            $policy = new $this->policies[$model]();
            if (method_exists($policy, $ability)) {
                return (bool) $policy->$ability($this->user, $arguments);
            }
        }

        if (isset($this->abilities[$ability])) {
            return (bool) ($this->abilities[$ability])($this->user, $arguments);
        }

        return false;
    }

    public function denies(string $ability, mixed $arguments = null): bool
    {
        return !$this->allows($ability, $arguments);
    }
}
