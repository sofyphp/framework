<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Validation\Validator;
use Tests\TestCase;

final class ValidationTest extends TestCase
{
    public function test_passes_valid_data(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.co', 'age' => '20'],
            ['email' => 'required|email', 'age' => 'required|numeric'],
        );
        $this->assertFalse($v->fails());
    }

    public function test_fails_and_reports_errors(): void
    {
        $v = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email', 'name' => 'required'],
        );
        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
    }
}
