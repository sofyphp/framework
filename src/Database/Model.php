<?php

declare(strict_types=1);

namespace Sofy\Database;

abstract class Model
{
    protected static string $table      = '';
    protected static string $primaryKey = 'id';
    protected static bool   $timestamps = true;
    protected static bool   $softDeletes = false;
    protected static array  $fillable   = [];
    protected static array  $hidden     = [];
    protected static array  $casts      = [];

    /** @var array<class-string, list<class-string>> */
    protected static array $observers = [];

    /** @var array<class-string, true> tracks which model classes have been booted */
    private static array $booted = [];

    private array $attributes     = [];
    private array $original       = [];
    private bool  $exists         = false;
    private array $relationsCache = [];

    public function __construct(array $attributes = [])
    {
        if (!isset(self::$booted[static::class])) {
            self::$booted[static::class] = true;
            static::boot();
        }
        $this->fill($attributes);
    }

    /**
     * Called once per model class on first instantiation.
     * Override in subclasses to register observers, etc.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Auto-call boot{TraitName}() for every trait used by this model.
     * Add the Auditable trait, and bootAuditable() is called automatically.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;
        $traits = [];
        do {
            $traits = array_merge(class_uses($class), $traits);
        } while ($class = get_parent_class($class));

        foreach ($traits as $trait) {
            $method = 'boot' . (new \ReflectionClass($trait))->getShortName();
            if (method_exists(static::class, $method)) {
                static::$method();
            }
        }
    }

    // ── Table / PK helpers ────────────────────────────────────────────────────

    public static function getTable(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }
        // UserProfile → user_profiles
        $short = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . 's';
    }

    public static function getPrimaryKeyName(): string
    {
        return static::$primaryKey;
    }

    // ── Attribute access ──────────────────────────────────────────────────────

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (empty(static::$fillable) || in_array($key, static::$fillable, true)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->castAttribute($key, $value);
    }

    public function getAttribute(string $key): mixed
    {
        // Accessor method: getNameAttribute()
        $accessor = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor();
        }
        // Raw attribute
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        // Cached relation
        if (array_key_exists($key, $this->relationsCache)) {
            return $this->relationsCache[$key];
        }
        // Lazy-load relation method (hasMany/hasOne/belongsTo/belongsToMany)
        if (method_exists($this, $key)) {
            $result = $this->$key();
            if ($result instanceof \Sofy\Database\Relations\Relation || $result instanceof BelongsToMany) {
                $loaded = $result->get();
                $this->relationsCache[$key] = $loaded;
                return $loaded;
            }
        }
        return null;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relationsCache[$name] = $value;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relationsCache[$name] ?? null;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relationsCache);
    }

    private function castAttribute(string $key, mixed $value): mixed
    {
        return match (static::$casts[$key] ?? null) {
            'int', 'integer'     => (int) $value,
            'float', 'double'    => (float) $value,
            'bool', 'boolean'    => (bool) $value,
            'string'             => (string) $value,
            'array', 'json'      => is_string($value) ? json_decode($value, true) : $value,
            'datetime'           => is_string($value) ? new \DateTimeImmutable($value) : $value,
            default              => $value,
        };
    }

    public function __get(string $key): mixed  { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }

    // ── Query factory ─────────────────────────────────────────────────────────

    /** @return Builder<static> */
    public static function query(): Builder
    {
        $builder = new Builder(static::class);
        if (static::$softDeletes) {
            $builder->whereNull('deleted_at');
        }
        return $builder;
    }

    /** Query including soft-deleted rows. */
    public static function withTrashed(): Builder
    {
        return new Builder(static::class);
    }

    /** Query only soft-deleted rows. */
    public static function onlyTrashed(): Builder
    {
        return (new Builder(static::class))->whereNotNull('deleted_at');
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->find($id);
    }

    public static function findOrFail(int|string $id): static
    {
        return static::query()->find($id)
            ?? throw new \RuntimeException(static::class . " #$id not found.");
    }

    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): Builder
    {
        $builder = static::query();
        return $value === null
            ? $builder->where($column, $operatorOrValue)
            : $builder->where($column, $operatorOrValue, $value);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $builder = static::query();
        foreach ($attributes as $col => $val) {
            $builder->where($col, $val);
        }
        return $builder->first() ?? new static(array_merge($attributes, $values));
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes, $values);
        if ($model->isNew()) {
            $model->save();
        }
        return $model;
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes);
        $model->fill(array_merge($attributes, $values));
        $model->save();
        return $model;
    }

    // ── Persistence ───────────────────────────────────────────────────────────

    public function save(): bool
    {
        if (!$this->fireEvent('saving')) {
            return false;
        }
        $result = $this->exists ? $this->performUpdate() : $this->performInsert();
        if ($result) {
            $this->fireEvent('saved');
        }
        return $result;
    }

    private function performInsert(): bool
    {
        if (!$this->fireEvent('creating')) {
            return false;
        }

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $this->attributes['created_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        $id = Connection::getDefault()->table(static::getTable())->insert($this->attributes);
        $this->attributes[static::$primaryKey] = $id;
        $this->original = $this->attributes;
        $this->exists   = true;

        $this->fireEvent('created');
        return true;
    }

    private function performUpdate(): bool
    {
        if (!$this->fireEvent('updating')) {
            return false;
        }

        if (static::$timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();
        if (empty($dirty)) {
            return true;
        }

        Connection::getDefault()
            ->table(static::getTable())
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->update($dirty);

        $this->original = $this->attributes;
        $this->fireEvent('updated');
        return true;
    }

    public function delete(): bool
    {
        if (!$this->fireEvent('deleting')) {
            return false;
        }

        if (static::$softDeletes) {
            $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
            $result = $this->performUpdate();
            if ($result) {
                $this->fireEvent('deleted');
            }
            return $result;
        }

        Connection::getDefault()
            ->table(static::getTable())
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->delete();

        $this->exists = false;
        $this->fireEvent('deleted');
        return true;
    }

    public function restore(): bool
    {
        $this->attributes['deleted_at'] = null;
        return $this->performUpdate();
    }

    public function trashed(): bool
    {
        return static::$softDeletes && isset($this->attributes['deleted_at']);
    }

    public static function destroy(int|string ...$ids): int
    {
        return Connection::getDefault()
            ->table(static::getTable())
            ->whereIn(static::$primaryKey, $ids)
            ->delete();
    }

    private function getDirty(): array
    {
        return $this->getChanges();
    }

    /** Returns attributes that differ from the original (loaded) state. */
    public function getChanges(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Returns the original attribute values as loaded from the database.
     * When $key is given, returns just that value (or null if not set).
     */
    public function getOriginal(?string $key = null): mixed
    {
        if ($key !== null) {
            return $this->original[$key] ?? null;
        }
        return $this->original;
    }

    // ── Hydration ─────────────────────────────────────────────────────────────

    public static function fromArray(array $data, bool $exists = false): static
    {
        $model             = new static();
        $model->attributes = $data;
        $model->original   = $data;
        $model->exists     = $exists;

        foreach (static::$casts as $key => $cast) {
            if (array_key_exists($key, $model->attributes)) {
                $model->attributes[$key] = $model->castAttribute($key, $model->attributes[$key]);
            }
        }

        return $model;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    protected function hasOne(string $related, string $foreignKey = '', string $localKey = ''): \Sofy\Database\Relations\HasOne
    {
        $localKey   = $localKey   ?: static::$primaryKey;
        $foreignKey = $foreignKey ?: $this->foreignKeyFor(static::class);
        return new \Sofy\Database\Relations\HasOne($related, $foreignKey, $localKey, $this->getAttribute($localKey));
    }

    protected function hasMany(string $related, string $foreignKey = '', string $localKey = ''): \Sofy\Database\Relations\HasMany
    {
        $localKey   = $localKey   ?: static::$primaryKey;
        $foreignKey = $foreignKey ?: $this->foreignKeyFor(static::class);
        return new \Sofy\Database\Relations\HasMany($related, $foreignKey, $localKey, $this->getAttribute($localKey));
    }

    protected function belongsTo(string $related, string $foreignKey = '', string $ownerKey = ''): \Sofy\Database\Relations\BelongsTo
    {
        $ownerKey   = $ownerKey   ?: static::getPrimaryKeyName();
        $foreignKey = $foreignKey ?: $this->foreignKeyFor($related);
        return new \Sofy\Database\Relations\BelongsTo($related, $foreignKey, $ownerKey, $this->getAttribute($foreignKey));
    }

    protected function hasManyThrough(
        string $related,
        string $through,
        string $firstKey  = '',
        string $secondKey = '',
        string $localKey  = '',
        string $throughKey = '',
    ): \Sofy\Database\Relations\HasManyThrough {
        $localKey   = $localKey   ?: static::$primaryKey;
        $throughKey = $throughKey ?: 'id';
        $firstKey   = $firstKey   ?: $this->foreignKeyFor(static::class);
        $secondKey  = $secondKey  ?: $this->foreignKeyFor($through);
        return new \Sofy\Database\Relations\HasManyThrough(
            $related, $through, $firstKey, $secondKey, $localKey, $throughKey,
            $this->getAttribute($localKey)
        );
    }

    protected function belongsToMany(
        string $related,
        string $pivotTable = '',
        string $foreignKey = '',
        string $relatedKey = '',
    ): BelongsToMany {
        if ($pivotTable === '') {
            $a = strtolower((new \ReflectionClass(static::class))->getShortName());
            $b = strtolower((new \ReflectionClass($related))->getShortName());
            $names = [$a, $b];
            sort($names);
            $pivotTable = implode('_', $names);
        }

        $foreignKey = $foreignKey ?: $this->foreignKeyFor(static::class);
        $relatedKey = $relatedKey ?: $this->foreignKeyFor($related);

        return new BelongsToMany(
            $related, $pivotTable, $foreignKey, $relatedKey,
            $this->getAttribute(static::$primaryKey), static::$primaryKey,
        );
    }

    protected function foreignKeyFor(string $class): string
    {
        $short = strtolower((new \ReflectionClass($class))->getShortName());
        return $short . '_id';
    }

    /** Eager-load relations: User::with('posts', 'profile')->get() */
    public static function with(string ...$relations): Builder
    {
        return static::query()->with(...$relations);
    }

    // ── Serialization ─────────────────────────────────────────────────────────

    public function toArray(): array
    {
        $data = $this->attributes;
        foreach (static::$hidden as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    public function getPrimaryKeyValue(): mixed
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function isNew(): bool { return !$this->exists; }

    // ── Query scopes ──────────────────────────────────────────────────────────

    /**
     * Route unknown static calls to local query scopes.
     *
     * Define a scope on your model as a public method named scopeXxx:
     *
     *   public function scopeActive(Builder $query): Builder {
     *       return $query->where('active', 1);
     *   }
     *
     * Then call: User::active()->get()
     */
    public static function __callStatic(string $name, array $args): Builder
    {
        $scope = 'scope' . ucfirst($name);
        $model = new static();

        if (method_exists($model, $scope)) {
            $builder = static::query();
            return $model->$scope($builder, ...$args) ?? $builder;
        }

        throw new \BadMethodCallException('Call to undefined method ' . static::class . '::' . $name . '()');
    }

    // ── Observers ─────────────────────────────────────────────────────────────

    /**
     * Register an observer class for this model.
     *
     * The observer may define any of these methods:
     *   creating, created, updating, updated, saving, saved,
     *   deleting, deleted, restoring, restored
     *
     * Usage:
     *   User::observe(UserObserver::class);
     *
     * @param class-string|object $observer
     */
    public static function observe(string|object $observer): void
    {
        $class = is_object($observer) ? $observer::class : $observer;
        self::$observers[static::class][] = $class;
    }

    /**
     * Fire a lifecycle event to all registered observers.
     * Returns false if any observer returns false (halts the operation).
     */
    private function fireEvent(string $event): bool
    {
        foreach (self::$observers[static::class] ?? [] as $observerClass) {
            $obs = new $observerClass();
            if (method_exists($obs, $event)) {
                if ($obs->$event($this) === false) {
                    return false;
                }
            }
        }
        return true;
    }
}
