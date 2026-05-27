<?php

declare(strict_types=1);

namespace Sofy\Validation;

class Validator
{
    private array $errors = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {}

    public static function make(array $data, array $rules, array $messages = []): static
    {
        return new static($data, $rules, $messages);
    }

    /**
     * Validates data and returns only the validated fields.
     *
     * @throws ValidationException
     */
    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleSet) {
            $this->validateField($field, $ruleSet);
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $this->extractValidated();
    }

    public function fails(): bool
    {
        try {
            $this->validate();
            return false;
        } catch (ValidationException) {
            return true;
        }
    }

    public function errors(): array
    {
        return $this->errors;
    }

    // ── Field validation ──────────────────────────────────────────────────────

    private function validateField(string $field, array|string $ruleSet): void
    {
        // Expand wildcard fields like 'users.*.email'
        if (str_contains($field, '*')) {
            $this->validateWildcard($field, $ruleSet);
            return;
        }

        $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
        $value = $this->getValue($field);

        // 'sometimes' — skip entirely if field is absent from the data
        if (in_array('sometimes', $rules, true) && !$this->hasKey($field)) {
            return;
        }

        foreach ($rules as $rule) {
            if ($rule === 'sometimes') {
                continue;
            }
            if ($rule instanceof Rule) {
                $this->applyCustomRule($field, $value, $rule);
            } else {
                $this->apply($field, $value, trim((string) $rule));
            }
        }
    }

    private function validateWildcard(string $field, array|string $ruleSet): void
    {
        $starPos = strpos($field, '*');
        $prefix  = rtrim(substr($field, 0, $starPos), '.');
        $suffix  = ltrim(substr($field, $starPos + 1), '.');

        $items = $this->getValue($prefix);
        if (!is_array($items)) {
            return;
        }

        foreach (array_keys($items) as $key) {
            $concreteField = $suffix !== '' ? "$prefix.$key.$suffix" : "$prefix.$key";
            $this->validateField($concreteField, $ruleSet);
        }
    }

    // ── Dot-notation data access ──────────────────────────────────────────────

    private function getValue(string $field): mixed
    {
        $keys  = explode('.', $field);
        $value = $this->data;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    private function hasKey(string $field): bool
    {
        $keys  = explode('.', $field);
        $value = $this->data;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return false;
            }
            $value = $value[$key];
        }
        return true;
    }

    // ── Rule dispatcher ───────────────────────────────────────────────────────

    private function apply(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        // Skip type/format rules for null values when field is nullable
        $skipForNull = !in_array($name, ['required', 'requiredIf', 'requiredUnless', 'nullable'], true);
        if ($value === null && $skipForNull && $this->isNullable($field)) {
            return;
        }

        match ($name) {
            'required'        => $this->required($field, $value),
            'requiredIf'      => $this->requiredIf($field, $value, (string) $param),
            'requiredUnless'  => $this->requiredUnless($field, $value, (string) $param),
            'string'          => $this->type($field, $value, 'string'),
            'int', 'integer'  => $this->type($field, $value, 'integer'),
            'numeric'         => $this->type($field, $value, 'numeric'),
            'bool', 'boolean' => $this->type($field, $value, 'boolean'),
            'array'           => $this->type($field, $value, 'array'),
            'email'           => $this->email($field, $value),
            'url'             => $this->url($field, $value),
            'min'             => $this->min($field, $value, (int) $param),
            'max'             => $this->max($field, $value, (int) $param),
            'size'            => $this->size($field, $value, (int) $param),
            'minLength'       => $this->minLength($field, $value, (int) $param),
            'maxLength'       => $this->maxLength($field, $value, (int) $param),
            'in'              => $this->in($field, $value, explode(',', $param ?? '')),
            'notIn'           => $this->notIn($field, $value, explode(',', $param ?? '')),
            'regex'           => $this->regex($field, $value, (string) $param),
            'confirmed'       => $this->confirmed($field, $value),
            'same'            => $this->same($field, $value, (string) $param),
            'different'       => $this->different($field, $value, (string) $param),
            default           => null,
        };
    }

    private function applyCustomRule(string $field, mixed $value, Rule $rule): void
    {
        if (!$rule->passes($field, $value)) {
            $this->err($field, $rule->message($field));
        }
    }

    // ── Rules ─────────────────────────────────────────────────────────────────

    private function required(string $f, mixed $v): void
    {
        if ($v === null || $v === '') {
            $this->err($f, "The $f field is required.");
        }
    }

    private function requiredIf(string $f, mixed $v, string $param): void
    {
        [$otherField, $otherValue] = array_pad(explode(',', $param, 2), 2, null);
        if ((string) $this->getValue((string) $otherField) === (string) $otherValue) {
            $this->required($f, $v);
        }
    }

    private function requiredUnless(string $f, mixed $v, string $param): void
    {
        [$otherField, $otherValue] = array_pad(explode(',', $param, 2), 2, null);
        if ((string) $this->getValue((string) $otherField) !== (string) $otherValue) {
            $this->required($f, $v);
        }
    }

    private function type(string $f, mixed $v, string $type): void
    {
        if ($v === null) return;
        $ok = match ($type) {
            'string'  => is_string($v),
            'integer' => filter_var($v, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($v),
            'boolean' => is_bool($v) || in_array($v, [0, 1, '0', '1', 'true', 'false'], true),
            'array'   => is_array($v),
            default   => true,
        };
        if (!$ok) $this->err($f, "The $f must be a $type.");
    }

    private function email(string $f, mixed $v): void
    {
        if ($v === null) return;
        if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            $this->err($f, "The $f must be a valid email address.");
        }
    }

    private function url(string $f, mixed $v): void
    {
        if ($v === null) return;
        if (filter_var($v, FILTER_VALIDATE_URL) === false) {
            $this->err($f, "The $f must be a valid URL.");
        }
    }

    private function min(string $f, mixed $v, int $min): void
    {
        if ($v === null) return;
        $n = is_array($v) ? count($v) : (is_numeric($v) ? (float) $v : mb_strlen((string) $v));
        if ($n < $min) $this->err($f, "The $f must be at least $min.");
    }

    private function max(string $f, mixed $v, int $max): void
    {
        if ($v === null) return;
        $n = is_array($v) ? count($v) : (is_numeric($v) ? (float) $v : mb_strlen((string) $v));
        if ($n > $max) $this->err($f, "The $f must not exceed $max.");
    }

    private function size(string $f, mixed $v, int $size): void
    {
        if ($v === null) return;
        $n = is_array($v) ? count($v) : (is_numeric($v) ? (float) $v : mb_strlen((string) $v));
        if ($n !== (float) $size) $this->err($f, "The $f must be $size.");
    }

    private function minLength(string $f, mixed $v, int $min): void
    {
        if ($v === null) return;
        if (mb_strlen((string) $v) < $min) {
            $this->err($f, "The $f must be at least $min characters.");
        }
    }

    private function maxLength(string $f, mixed $v, int $max): void
    {
        if ($v === null) return;
        if (mb_strlen((string) $v) > $max) {
            $this->err($f, "The $f must not exceed $max characters.");
        }
    }

    private function in(string $f, mixed $v, array $opts): void
    {
        if ($v === null) return;
        if (!in_array((string) $v, $opts, true)) {
            $this->err($f, "The $f must be one of: " . implode(', ', $opts) . '.');
        }
    }

    private function notIn(string $f, mixed $v, array $opts): void
    {
        if ($v === null) return;
        if (in_array((string) $v, $opts, true)) {
            $this->err($f, "The $f contains an invalid value.");
        }
    }

    private function regex(string $f, mixed $v, string $pattern): void
    {
        if ($v === null) return;
        if (!preg_match($pattern, (string) $v)) {
            $this->err($f, "The $f format is invalid.");
        }
    }

    private function confirmed(string $f, mixed $v): void
    {
        if (($this->data[$f . '_confirmation'] ?? null) !== $v) {
            $this->err($f, "The $f confirmation does not match.");
        }
    }

    private function same(string $f, mixed $v, string $other): void
    {
        if ($v !== $this->getValue($other)) {
            $this->err($f, "The $f must match $other.");
        }
    }

    private function different(string $f, mixed $v, string $other): void
    {
        if ($v === $this->getValue($other)) {
            $this->err($f, "The $f must be different from $other.");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isNullable(string $field): bool
    {
        $rules = $this->rules[$field] ?? [];
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        return in_array('nullable', $rules, true);
    }

    private function err(string $field, string $message): void
    {
        $custom = $this->messages[$field] ?? null;
        $this->errors[$field][] = $custom ?? $message;
    }

    // ── Validated data extraction ─────────────────────────────────────────────

    private function extractValidated(): array
    {
        $result = [];
        foreach ($this->rules as $field => $ruleSet) {
            if (str_contains($field, '*')) {
                $this->extractWildcard($field, $result);
            } elseif ($this->hasKey($field)) {
                $this->setNestedValue($result, $field, $this->getValue($field));
            }
        }
        return $result;
    }

    private function extractWildcard(string $field, array &$result): void
    {
        $starPos = strpos($field, '*');
        $prefix  = rtrim(substr($field, 0, $starPos), '.');
        $suffix  = ltrim(substr($field, $starPos + 1), '.');
        $items   = $this->getValue($prefix);

        if (!is_array($items)) return;

        foreach (array_keys($items) as $key) {
            $concreteField = $suffix !== '' ? "$prefix.$key.$suffix" : "$prefix.$key";
            if ($this->hasKey($concreteField)) {
                $this->setNestedValue($result, $concreteField, $this->getValue($concreteField));
            }
        }
    }

    private function setNestedValue(array &$target, string $field, mixed $value): void
    {
        $keys = explode('.', $field);
        $ref  = &$target;
        foreach ($keys as $key) {
            if (!array_key_exists($key, $ref) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $value;
    }
}
