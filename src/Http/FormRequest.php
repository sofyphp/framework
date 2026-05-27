<?php

declare(strict_types=1);

namespace Sofy\Http;

use Sofy\Validation\ValidationException;
use Sofy\Validation\Validator;

/**
 * Dedicated validation request class.
 *
 * Usage:
 *   class StoreUserRequest extends FormRequest
 *   {
 *       public function rules(): array
 *       {
 *           return [
 *               'name'  => 'required|min:2|max:100',
 *               'email' => 'required|email|unique:users',
 *           ];
 *       }
 *   }
 *
 *   // In controller:
 *   public function store(StoreUserRequest $request): Response
 *   {
 *       $data = $request->validated();
 *   }
 */
abstract class FormRequest extends Request
{
    private ?array $validatedData = null;

    /**
     * Validation rules.
     * @return array<string, string|array<string>>
     */
    abstract public function rules(): array;

    /**
     * Custom validation messages (optional override).
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Authorization check. Override to restrict access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data before validation (optional override).
     */
    protected function prepareForValidation(): void {}

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * @throws \RuntimeException  if authorize() returns false
     * @throws ValidationException
     */
    public function validated(): array
    {
        if ($this->validatedData !== null) {
            return $this->validatedData;
        }

        if (!$this->authorize()) {
            throw new \RuntimeException('This action is unauthorized.', 403);
        }

        $this->prepareForValidation();

        $validator = Validator::make($this->all(), $this->rules(), $this->messages());
        $this->validatedData = $validator->validate();

        return $this->validatedData;
    }

    public function passes(): bool
    {
        try {
            $this->validated();
            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function createFrom(Request $request): static
    {
        $instance = new static(
            query:   [],
            body:    [],
            server:  [],
            files:   [],
            cookies: [],
        );

        // Copy all data by resetting internals via reflection
        $ref = new \ReflectionClass(Request::class);

        foreach (['query', 'body', 'server', 'files', 'cookies'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue($instance, $p->getValue($request));
        }

        $routeParams = $ref->getProperty('routeParams');
        $routeParams->setAccessible(true);
        $routeParams->setValue($instance, $routeParams->getValue($request));

        return $instance;
    }
}
