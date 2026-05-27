<?php

declare(strict_types=1);

namespace Sofy\Events;

class Dispatcher
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    /** @var array<string, callable[]> */
    private array $wildcards = [];

    /**
     * Pre-compiled regex patterns for wildcard listeners.
     * Built once at listen() time — avoids repeated preg_quote + str_replace on every dispatch.
     *
     * @var array<string, string>  pattern → regex
     */
    private array $wildcardRegex = [];

    private static ?self $instance = null;

    public static function getInstance(): static
    {
        static::$instance ??= new static();
        return static::$instance;
    }

    /** Replace the singleton — used by EventFake in tests. */
    public static function setInstance(static $instance): void
    {
        static::$instance = $instance;
    }

    public static function resetInstance(): void
    {
        static::$instance = null;
    }

    // ── Register ──────────────────────────────────────────────────────────────

    public function listen(string $event, callable $listener): void
    {
        if (str_contains($event, '*')) {
            $this->wildcards[$event][] = $listener;
            // Compile regex once at registration — not on every dispatch
            $this->wildcardRegex[$event] ??=
                '/^' . str_replace('\*', '.*', preg_quote($event, '/')) . '$/';
        } else {
            $this->listeners[$event][] = $listener;
        }
    }

    public function listenOnce(string $event, callable $listener): void
    {
        $wrapper = null;
        $wrapper = function () use ($event, $listener, &$wrapper) {
            $this->forget($event, $wrapper);
            return $listener(...func_get_args());
        };
        $this->listen($event, $wrapper);
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    /**
     * Fire an event. Accepts a string name with a payload array,
     * or a typed Event object (uses class name as event name).
     *
     * @return array<mixed>  responses from all listeners
     */
    public function dispatch(string|object $event, array $payload = []): array
    {
        if (is_object($event)) {
            $payload = [$event];
            $name    = get_class($event);
        } else {
            $name = $event;
        }

        $responses = [];

        foreach ($this->getListeners($name) as $listener) {
            $response = $listener(...$payload);
            $responses[] = $response;

            // Support propagation stop on typed events
            if (is_object($payload[0] ?? null)
                && $payload[0] instanceof Event
                && $payload[0]->isPropagationStopped()
            ) {
                break;
            }
        }

        return $responses;
    }

    /** @return callable[] */
    public function getListeners(string $event): array
    {
        $direct    = $this->listeners[$event] ?? [];
        $wildcards = [];

        foreach ($this->wildcards as $pattern => $listeners) {
            // Use pre-compiled regex — built once in listen(), not rebuilt here
            if (preg_match($this->wildcardRegex[$pattern], $event)) {
                $wildcards = array_merge($wildcards, $listeners);
            }
        }

        return array_merge($wildcards, $direct);
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->getListeners($event));
    }

    // ── Remove ────────────────────────────────────────────────────────────────

    public function forget(string $event, ?callable $listener = null): void
    {
        if ($listener === null) {
            unset($this->listeners[$event], $this->wildcards[$event]);
            return;
        }

        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_values(
                array_filter($this->listeners[$event], fn($l) => $l !== $listener)
            );
        }
    }

    public function forgetAll(): void
    {
        $this->listeners = [];
        $this->wildcards = [];
    }
}
