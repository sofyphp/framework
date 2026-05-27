<?php

declare(strict_types=1);

namespace Sofy\Audit;

use Sofy\Auth\Auth;
use Sofy\Database\Model;

/**
 * Automatically registered by the Auditable trait.
 * Hooks into Model lifecycle events to record changes.
 */
class AuditObserver
{
    /** Log after a new record is inserted. */
    public function created(Model $model): void
    {
        AuditLog::log('created', $model, [], $this->visible($model, $model->toArray()), $this->userId());
    }

    /**
     * Log BEFORE an update so we can capture original vs dirty.
     * (After 'updated', $original is already synced to $attributes.)
     */
    public function updating(Model $model): void
    {
        $old = $this->visible($model, $model->getOriginal());
        $new = $this->visible($model, $model->getChanges());

        if (empty($new)) {
            return; // nothing actually changed
        }

        AuditLog::log('updated', $model, $old, $new, $this->userId());
    }

    /** Log BEFORE delete so we still have the model data. */
    public function deleting(Model $model): void
    {
        AuditLog::log('deleted', $model, $this->visible($model, $model->toArray()), [], $this->userId());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Strip audit-excluded fields from an attribute array. */
    private function visible(Model $model, array $attributes): array
    {
        $exclude = $model->auditExclude ?? [];
        foreach ($exclude as $key) {
            unset($attributes[$key]);
        }
        return $attributes;
    }

    private function userId(): ?int
    {
        try {
            return Auth::id();
        } catch (\Throwable) {
            return null;
        }
    }
}
