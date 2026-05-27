<?php

declare(strict_types=1);

namespace Sofy\Database;

readonly class RawExpression
{
    public function __construct(public string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
