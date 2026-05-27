<?php

declare(strict_types=1);

namespace Sofy\Audit;

use Sofy\Database\Connection;

/**
 * AuditLog — stores and queries a record of model changes.
 *
 * Requires an `audit_logs` table. Create it with:
 *   php sofy make:migration create_audit_logs_table
 *
 * Then add to the migration:
 *
 *   CREATE TABLE audit_logs (
 *     id         INTEGER PRIMARY KEY AUTO_INCREMENT,
 *     model_type VARCHAR(255) NOT NULL,
 *     model_id   VARCHAR(64)  NOT NULL,
 *     event      VARCHAR(32)  NOT NULL,    -- created | updated | deleted
 *     old_values TEXT,
 *     new_values TEXT,
 *     user_id    INTEGER      DEFAULT NULL,
 *     created_at DATETIME     NOT NULL
 *   );
 */
class AuditLog
{
    private const string TABLE = 'audit_logs';

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Record a single change event.
     *
     * @param string     $event      'created' | 'updated' | 'deleted'
     * @param object     $model      The model instance
     * @param array      $old        Attributes before the change
     * @param array      $new        Attributes after the change
     * @param mixed|null $userId     ID of the user who made the change (null = system)
     */
    public static function log(
        string $event,
        object $model,
        array  $old    = [],
        array  $new    = [],
        mixed  $userId = null,
    ): void {
        try {
            Connection::getDefault()->table(self::TABLE)->insert([
                'model_type' => $model::class,
                'model_id'   => (string) ($model->getPrimaryKeyValue() ?? ''),
                'event'      => $event,
                'old_values' => $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'new_values' => $new ? json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'user_id'    => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Silently ignore if audit_logs table doesn't exist yet
        }
    }

    // ── Query ─────────────────────────────────────────────────────────────────

    /**
     * Return all audit entries for a model class + optional ID.
     *
     * @param object|class-string $model
     */
    public static function for(object|string $model, mixed $id = null): array
    {
        $type  = is_object($model) ? $model::class : $model;
        $query = Connection::getDefault()->table(self::TABLE)
            ->where('model_type', $type)
            ->orderBy('id', 'desc');

        if ($id !== null || is_object($model)) {
            $resolvedId = $id ?? (is_object($model) ? $model->getPrimaryKeyValue() : null);
            if ($resolvedId !== null) {
                $query->where('model_id', (string) $resolvedId);
            }
        }

        return array_map([self::class, 'hydrate'], $query->get());
    }

    /**
     * Return the most recent audit entries across all models.
     */
    public static function recent(int $limit = 50): array
    {
        return array_map(
            [self::class, 'hydrate'],
            Connection::getDefault()->table(self::TABLE)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
        );
    }

    /**
     * Return audit entries made by a specific user.
     */
    public static function forUser(mixed $userId, int $limit = 50): array
    {
        return array_map(
            [self::class, 'hydrate'],
            Connection::getDefault()->table(self::TABLE)
                ->where('user_id', $userId)
                ->orderBy('id', 'desc')
                ->limit($limit)
                ->get()
        );
    }

    // ── Hydration ─────────────────────────────────────────────────────────────

    private static function hydrate(array $row): array
    {
        $row['old_values'] = $row['old_values'] ? json_decode($row['old_values'], true) : [];
        $row['new_values'] = $row['new_values'] ? json_decode($row['new_values'], true) : [];
        return $row;
    }
}
