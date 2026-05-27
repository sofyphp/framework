<?php

declare(strict_types=1);

namespace Sofy\Security;

use RuntimeException;

/**
 * AES-256-GCM authenticated encryption.
 *
 * Key: 32-byte base64-encoded string from APP_KEY env variable.
 */
class Crypt
{
    private const string CIPHER  = 'aes-256-gcm';
    private const int    TAG_LEN = 16;

    // ── Key management ────────────────────────────────────────────────────────

    private static function key(): string
    {
        $appKey = (string) ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?? '');

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is not set. Generate one with: php sofy key:generate');
        }

        $raw = base64_decode(str_starts_with($appKey, 'base64:') ? substr($appKey, 7) : $appKey, true);

        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('APP_KEY must be a 32-byte base64-encoded string.');
        }

        return $raw;
    }

    // ── Encrypt / Decrypt ─────────────────────────────────────────────────────

    public static function encrypt(mixed $value): string
    {
        $iv  = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            serialize($value),
            self::CIPHER,
            static::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $payload = base64_encode(json_encode([
            'iv'   => base64_encode($iv),
            'val'  => base64_encode($ciphertext),
            'tag'  => base64_encode($tag),
            'mac'  => static::mac($iv, $ciphertext),
        ], JSON_THROW_ON_ERROR));

        return $payload;
    }

    public static function decrypt(string $payload): mixed
    {
        $data = json_decode(base64_decode($payload), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['iv'], $data['val'], $data['tag'], $data['mac'])) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        $iv         = base64_decode($data['iv']);
        $ciphertext = base64_decode($data['val']);
        $tag        = base64_decode($data['tag']);

        if (!hash_equals(static::mac($iv, $ciphertext), $data['mac'])) {
            throw new RuntimeException('MAC verification failed — payload may have been tampered.');
        }

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            static::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed.');
        }

        return unserialize($decrypted);
    }

    // ── String shortcuts ──────────────────────────────────────────────────────

    public static function encryptString(string $value): string
    {
        return static::encrypt($value);
    }

    public static function decryptString(string $payload): string
    {
        return (string) static::decrypt($payload);
    }

    // ── Key generation ────────────────────────────────────────────────────────

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private static function mac(string $iv, string $ciphertext): string
    {
        return hash_hmac('sha256', $iv . $ciphertext, static::key());
    }
}
