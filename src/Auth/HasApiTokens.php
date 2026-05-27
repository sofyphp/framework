<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Database\Connection;
use Sofy\Database\Model;

/**
 * API token authentication (Sanctum-lite).
 *
 * Add to User model:
 *   use Sofy\Auth\HasApiTokens;
 *   class User extends Model { use HasApiTokens; }
 *
 * Requires migration: php sofy make:migration create_personal_access_tokens_table
 *
 * Table schema:
 *   id, tokenable_type, tokenable_id, name, token (sha256 hash),
 *   abilities (JSON), last_used_at, created_at, updated_at
 *
 * Usage:
 *   $plainText = $user->createToken('mobile-app')->plainTextToken;
 *   $user->tokens()
 *   $user->revokeAllTokens()
 *
 * Auth via header: Authorization: Bearer {plainTextToken}
 *
 * @mixin Model
 */
trait HasApiTokens
{
    private ?object $currentAccessToken = null;

    /**
     * Create a new personal access token.
     * Returns an object with ->plainTextToken (show once) and ->token (stored hash).
     */
    public function createToken(string $name, array $abilities = ['*']): object
    {
        $plain = bin2hex(random_bytes(40));
        $hash  = hash('sha256', $plain);

        $id = Connection::getDefault()->table('personal_access_tokens')->insert([
            'tokenable_type' => static::class,
            'tokenable_id'   => $this->getAttribute(static::getPrimaryKeyName()),
            'name'           => $name,
            'token'          => $hash,
            'abilities'      => json_encode($abilities),
            'last_used_at'   => null,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (object) [
            'plainTextToken' => $id . '|' . $plain,
            'token'          => $hash,
            'id'             => $id,
        ];
    }

    public function tokens(): array
    {
        return Connection::getDefault()->query(
            'SELECT * FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?',
            [static::class, $this->getAttribute(static::getPrimaryKeyName())]
        );
    }

    public function revokeAllTokens(): void
    {
        Connection::getDefault()->execute(
            'DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?',
            [static::class, $this->getAttribute(static::getPrimaryKeyName())]
        );
    }

    public function tokenCan(string $ability): bool
    {
        if ($this->currentAccessToken === null) return false;
        $abilities = (array) json_decode($this->currentAccessToken->abilities ?? '["*"]', true);
        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function withAccessToken(object $token): static
    {
        $this->currentAccessToken = $token;
        return $this;
    }

    public function currentAccessToken(): ?object
    {
        return $this->currentAccessToken;
    }

    // ── Static token lookup ───────────────────────────────────────────────────

    /**
     * Find the user from a plain-text bearer token.
     * Used by TokenGuard — not typically called directly.
     */
    public static function findByToken(string $plainText): ?static
    {
        [$id, $plain] = array_pad(explode('|', $plainText, 2), 2, '');
        if (!$id || !$plain) return null;

        $hash = hash('sha256', $plain);

        $row = Connection::getDefault()->query(
            'SELECT * FROM personal_access_tokens WHERE id = ? AND token = ?',
            [(int) $id, $hash]
        )[0] ?? null;

        if (!$row) return null;

        // Touch last_used_at
        Connection::getDefault()->execute(
            'UPDATE personal_access_tokens SET last_used_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $row['id']]
        );

        $user = static::find((int) $row['tokenable_id']);
        return $user?->withAccessToken((object) $row);
    }
}
