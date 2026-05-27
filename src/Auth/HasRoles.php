<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Database\Connection;
use Sofy\Database\Model;
use RuntimeException;

/**
 * Trait for Role-Based Access Control.
 *
 * @mixin Model
 *
 * Add to your User model:
 *   use Sofy\Auth\HasRoles;
 *   class User extends Model { use HasRoles; }
 *
 * Requires tables: roles (id, name, slug), role_user (role_id, user_id).
 */
trait HasRoles
{
    /** @var array<int, array>|null cached roles for this instance */
    private ?array $cachedRoles = null;

    public function roles(): array
    {
        if ($this->cachedRoles !== null) {
            return $this->cachedRoles;
        }

        $pk  = static::getPrimaryKeyName();
        $uid = $this->getAttribute($pk);

        return $this->cachedRoles = Connection::getDefault()->query(
            'SELECT r.* FROM roles r
             INNER JOIN role_user ru ON ru.role_id = r.id
             WHERE ru.user_id = ?',
            [$uid]
        );
    }

    public function hasRole(string|array $role): bool
    {
        $slugs = array_column($this->roles(), 'slug');
        $check = is_array($role) ? $role : [$role];
        foreach ($check as $r) {
            if (in_array($r, $slugs, true)) return true;
        }
        return false;
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    public function hasAllRoles(array $roles): bool
    {
        $slugs = array_column($this->roles(), 'slug');
        foreach ($roles as $role) {
            if (!in_array($role, $slugs, true)) return false;
        }
        return true;
    }

    public function assignRole(string $slug): void
    {
        $role = Connection::getDefault()->query(
            'SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug]
        )[0] ?? null;

        if (!$role) {
            throw new RuntimeException("Role [$slug] not found.");
        }

        $pk  = static::getPrimaryKeyName();
        $uid = $this->getAttribute($pk);

        Connection::getDefault()->execute(
            'INSERT IGNORE INTO role_user (role_id, user_id) VALUES (?, ?)',
            [$role['id'], $uid]
        );

        $this->cachedRoles = null;
    }

    public function removeRole(string $slug): void
    {
        $role = Connection::getDefault()->query(
            'SELECT id FROM roles WHERE slug = ? LIMIT 1', [$slug]
        )[0] ?? null;

        if (!$role) return;

        $pk  = static::getPrimaryKeyName();
        $uid = $this->getAttribute($pk);

        Connection::getDefault()->execute(
            'DELETE FROM role_user WHERE role_id = ? AND user_id = ?',
            [$role['id'], $uid]
        );

        $this->cachedRoles = null;
    }

    public function syncRoles(array $slugs): void
    {
        $pk  = static::getPrimaryKeyName();
        $uid = $this->getAttribute($pk);

        Connection::getDefault()->execute(
            'DELETE FROM role_user WHERE user_id = ?', [$uid]
        );

        $this->cachedRoles = null;

        foreach ($slugs as $slug) {
            $this->assignRole($slug);
        }
    }
}
