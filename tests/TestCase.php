<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Sofy\Database\Connection;

/**
 * Base test case. Spins up a throwaway in-memory SQLite connection so model /
 * database tests run with no external services and reset between tests.
 */
abstract class TestCase extends BaseTestCase
{
    protected ?Connection $db = null;

    /** Create an in-memory SQLite DB and set it as the default connection. */
    protected function freshDatabase(): Connection
    {
        $this->db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        Connection::setDefault($this->db);
        return $this->db;
    }

    protected function exec(string $sql): void
    {
        $this->db?->execute($sql);
    }
}
