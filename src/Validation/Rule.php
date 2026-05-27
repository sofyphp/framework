<?php

declare(strict_types=1);

namespace Sofy\Validation;

/**
 * Interface for custom validation rules.
 *
 * Usage:
 *   class Uppercase implements Rule
 *   {
 *       public function passes(string $field, mixed $value): bool
 *       {
 *           return $value === strtoupper((string) $value);
 *       }
 *
 *       public function message(string $field): string
 *       {
 *           return "The $field must be uppercase.";
 *       }
 *   }
 *
 *   $validator = Validator::make($data, [
 *       'code' => ['required', new Uppercase()],
 *   ]);
 */
interface Rule
{
    public function passes(string $field, mixed $value): bool;

    public function message(string $field): string;
}
