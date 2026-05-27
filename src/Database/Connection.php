<?php

declare(strict_types=1);

namespace Sofy\Database;

use PDO;

class Connection
{
    private static ?Connection $default = null;
    private PDO $pdo;

    /**
     * Prepared statement cache — reuse PDOStatement objects for identical SQL.
     * PDO re-execution with different bindings is safe; only the SQL structure matters.
     *
     * @var array<string, \PDOStatement>
     */
    private array $stmtCache = [];

    public function __construct(array $config)
    {
        $this->pdo = new PDO(
            $this->buildDsn($config),
            $config['username'] ?? 'root',
            $config['password'] ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    private function buildDsn(array $config): string
    {
        return match ($config['driver'] ?? 'mysql') {
            'mysql'  => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host']     ?? '127.0.0.1',
                $config['port']     ?? '3306',
                $config['database'] ?? '',
                $config['charset']  ?? 'utf8mb4'
            ),
            'pgsql'  => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host']     ?? '127.0.0.1',
                $config['port']     ?? '5432',
                $config['database'] ?? ''
            ),
            'sqlite' => 'sqlite:' . ($config['database'] ?? ':memory:'),
            default  => throw new \InvalidArgumentException("Unsupported driver: {$config['driver']}"),
        };
    }

    public static function setDefault(Connection $connection): void
    {
        static::$default = $connection;
    }

    public static function getDefault(): static
    {
        return static::$default
            ?? throw new \RuntimeException('No database connection configured.');
    }

    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($this))->table($table);
    }

    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->stmtCache[$sql] ??= $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->stmtCache[$sql] ??= $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string|false
    {
        return $this->pdo->lastInsertId();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
