<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Security\Crypt;
use Sofy\Security\Hash;
use Tests\TestCase;

final class SecurityCryptTest extends TestCase
{
    public function test_hash_make_and_check(): void
    {
        $hash = Hash::make('secret123');
        $this->assertNotSame('secret123', $hash);
        $this->assertTrue(Hash::check('secret123', $hash));
        $this->assertFalse(Hash::check('wrong', $hash));
        $this->assertFalse(Hash::check('secret123', ''));
    }

    public function test_crypt_roundtrip(): void
    {
        $plain = 'sensitive value ✓ ё';
        $enc   = Crypt::encrypt($plain);
        $this->assertNotSame($plain, $enc);
        $this->assertSame($plain, Crypt::decrypt($enc));
    }

    public function test_crypt_tamper_is_detected(): void
    {
        $enc = Crypt::encrypt('value');
        // Flip a chunk in the middle of the base64 payload.
        $tampered = substr($enc, 0, 20) . 'AAAA' . substr($enc, 24);
        $this->expectException(\Throwable::class);
        Crypt::decrypt($tampered);
    }
}
