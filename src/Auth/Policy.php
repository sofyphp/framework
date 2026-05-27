<?php

declare(strict_types=1);

namespace Sofy\Auth;

/**
 * Base class for authorization policies.
 *
 * Create a policy with: php sofy make:policy PostPolicy --model=Post
 *
 * Methods match Gate ability names:
 *   public function view($user, Post $post): bool { ... }
 *   public function update($user, Post $post): bool { ... }
 *   public function delete($user, Post $post): bool { ... }
 *
 * Return true/false to allow/deny, or null to fall through to the next check.
 *
 * Register in a ServiceProvider or boot file:
 *   Gate::policy(Post::class, PostPolicy::class);
 */
abstract class Policy
{
    /**
     * Runs before every check on this policy.
     * Return true to allow, false to deny, null to continue to method.
     */
    public function before(mixed $user, string $ability): ?bool
    {
        return null;
    }
}
