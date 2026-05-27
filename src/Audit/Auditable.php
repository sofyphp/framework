<?php

declare(strict_types=1);

namespace Sofy\Audit;

/**
 * Auditable — add to any Model to automatically record all changes.
 *
 * Usage:
 *
 *   class User extends Model
 *   {
 *       use Auditable;
 *
 *       // Exclude sensitive fields from audit records (optional)
 *       public array $auditExclude = ['password', 'remember_token'];
 *   }
 *
 * Then query audit history:
 *
 *   AuditLog::for(User::class)          // all User changes
 *   AuditLog::for($user)                // changes for one user
 *   AuditLog::recent(20)                // last 20 changes across all models
 *   AuditLog::forUser(Auth::id(), 50)   // changes made by current user
 */
trait Auditable
{
    /**
     * Fields excluded from audit records.
     * Override in the model class to customise.
     *
     * @var string[]
     */
    public array $auditExclude = [];

    /**
     * Called automatically once per model class by Model::bootTraits().
     * Registers the AuditObserver to capture create / update / delete events.
     */
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /**
     * Retrieve the complete audit trail for this specific model instance.
     *
     * @return array<int, array{
     *     id: int,
     *     model_type: string,
     *     model_id: string,
     *     event: string,
     *     old_values: array,
     *     new_values: array,
     *     user_id: int|null,
     *     created_at: string,
     * }>
     */
    public function auditTrail(): array
    {
        return AuditLog::for($this);
    }

    /**
     * Check whether a specific field has changed since the last save.
     * Useful for conditional logic after loading from DB.
     */
    public function wasChanged(string ...$fields): bool
    {
        $changes = $this->getChanges();
        foreach ($fields as $field) {
            if (array_key_exists($field, $changes)) {
                return true;
            }
        }
        return false;
    }
}
