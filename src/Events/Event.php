<?php

declare(strict_types=1);

namespace Sofy\Events;

/**
 * Base class for typed events.
 *
 * Usage:
 *   class UserRegistered extends Event
 *   {
 *       public function __construct(public readonly User $user) {}
 *   }
 *
 *   event(new UserRegistered($user));
 */
abstract class Event
{
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
