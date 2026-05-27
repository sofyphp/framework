<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Database\Connection;

/**
 * Manages password-reset tokens.
 *
 * Table: password_reset_tokens (email, token, created_at)
 *
 * Usage:
 *   $plain = PasswordBroker::createToken('alice@example.com');
 *   // send $plain to the user via email
 *
 *   if (PasswordBroker::reset($email, $token, function($user, $password) {
 *       $user->password = Hash::make($password);
 *       $user->save();
 *   })) { ... }
 */
class PasswordBroker
{
    private static int $ttlMinutes = 60;

    public static function createToken(string $email): string
    {
        $plain = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        $db = Connection::getDefault();
        $db->execute('DELETE FROM password_reset_tokens WHERE email = ?', [$email]);
        $db->execute(
            'INSERT INTO password_reset_tokens (email, token, created_at) VALUES (?, ?, ?)',
            [$email, $hash, date('Y-m-d H:i:s')]
        );

        return $plain;
    }

    public static function validate(string $email, string $plainToken): bool
    {
        $rows = Connection::getDefault()->query(
            'SELECT token, created_at FROM password_reset_tokens WHERE email = ? LIMIT 1',
            [$email]
        );

        if (empty($rows)) {
            return false;
        }

        $row     = $rows[0];
        $expires = strtotime($row['created_at']) + self::$ttlMinutes * 60;

        if (time() > $expires) {
            return false;
        }

        return hash_equals($row['token'], hash('sha256', $plainToken));
    }

    public static function reset(string $email, string $plainToken, callable $callback): bool
    {
        if (!static::validate($email, $plainToken)) {
            return false;
        }

        $callback($email);

        static::deleteToken($email);
        return true;
    }

    public static function deleteToken(string $email): void
    {
        Connection::getDefault()->execute(
            'DELETE FROM password_reset_tokens WHERE email = ?',
            [$email]
        );
    }
}
