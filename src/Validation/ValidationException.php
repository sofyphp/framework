<?php

declare(strict_types=1);

namespace Sofy\Validation;

use RuntimeException;

class ValidationException extends RuntimeException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The given data was invalid.');
    }

    /** ['field' => ['msg1', 'msg2'], ...] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Первая ошибка среди всех полей. */
    public function firstError(): string
    {
        $first = reset($this->errors);
        return is_array($first) ? (string) reset($first) : (string) $first;
    }
}
