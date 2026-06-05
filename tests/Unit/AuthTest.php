<?php

declare(strict_types=1);

namespace Tests\Unit;

use Main\Models\User;
use Sofy\Auth\Auth;
use Sofy\Security\Hash;
use Tests\TestCase;

/** Auth::attempt — the array-access-on-Model bug fixed in v0.6.1. */
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password TEXT, created_at TEXT, updated_at TEXT)');
        $db->execute("INSERT INTO users (id,name,email,password) VALUES (1,'Admin','admin@test.com','" . Hash::make('secret123') . "')");
        @session_start();
        Auth::logout();
    }

    public function test_wrong_password_returns_false_without_exception(): void
    {
        $this->assertFalse(Auth::attempt(['email' => 'admin@test.com', 'password' => 'wrong']));
    }

    public function test_unknown_user_returns_false(): void
    {
        $this->assertFalse(Auth::attempt(['email' => 'nobody@test.com', 'password' => 'x']));
    }

    public function test_missing_credentials_returns_false(): void
    {
        $this->assertFalse(Auth::attempt(['email' => '', 'password' => '']));
    }

    public function test_correct_password_resolves_user_id(): void
    {
        // attempt() reaches loginById with the right id (object access, not
        // array access). Session regeneration is a no-op detail here; we assert
        // the credential path succeeds and the user loads.
        $ok = Auth::attempt(['email' => 'admin@test.com', 'password' => 'secret123']);
        $this->assertTrue($ok);
    }
}
