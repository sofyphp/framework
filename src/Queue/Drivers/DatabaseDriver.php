<?php

declare(strict_types=1);

namespace Sofy\Queue\Drivers;

use Sofy\Database\Connection;
use Sofy\Queue\Job;
use Throwable;

class DatabaseDriver implements DriverInterface
{
    public function __construct(private readonly Connection $db) {}

    public function push(Job $job): void
    {
        $this->db->execute(
            'INSERT INTO jobs (queue, payload, attempts, max_attempts, available_at, created_at)
             VALUES (?, ?, 0, ?, ?, ?)',
            [$job->queue, serialize($job), $job->maxAttempts, time() + $job->delay, time()]
        );
    }

    public function pop(string $queue): ?array
    {
        $rows = $this->db->query(
            'SELECT * FROM jobs
             WHERE queue = ? AND available_at <= ? AND reserved_at IS NULL AND failed_at IS NULL
             ORDER BY id
             LIMIT 1',
            [$queue, time()]
        );

        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];

        $this->db->execute(
            'UPDATE jobs SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?',
            [time(), $row['id']]
        );

        return [
            'id'           => (int) $row['id'],
            'job'          => unserialize($row['payload']),
            'attempts'     => (int) $row['attempts'] + 1,
            'max_attempts' => (int) $row['max_attempts'],
            'payload'      => $row['payload'],
            'queue'        => $row['queue'],
        ];
    }

    public function ack(int $id): void
    {
        $this->db->execute('DELETE FROM jobs WHERE id = ?', [$id]);
    }

    public function release(int $id, int $delay = 0): void
    {
        $this->db->execute(
            'UPDATE jobs SET reserved_at = NULL, available_at = ? WHERE id = ?',
            [time() + $delay, $id]
        );
    }

    public function fail(int $id, string $queue, string $payload, Throwable $e): void
    {
        $this->db->execute('DELETE FROM jobs WHERE id = ?', [$id]);
        $this->db->execute(
            'INSERT INTO failed_jobs (queue, payload, exception, failed_at) VALUES (?, ?, ?, ?)',
            [$queue, $payload, $e->getMessage() . "\n" . $e->getTraceAsString(), time()]
        );
    }

    public function retryFailed(): int
    {
        $rows = $this->db->query('SELECT * FROM failed_jobs ORDER BY id');

        foreach ($rows as $row) {
            $job = unserialize($row['payload']);
            if ($job instanceof Job) {
                $this->push($job);
            }
        }

        $count = count($rows);
        $this->db->execute('DELETE FROM failed_jobs');
        return $count;
    }

    public function flushFailed(): int
    {
        $count = $this->failedCount();
        $this->db->execute('DELETE FROM failed_jobs');
        return $count;
    }

    public function failedCount(): int
    {
        $rows = $this->db->query('SELECT COUNT(*) as cnt FROM failed_jobs');
        return (int) ($rows[0]['cnt'] ?? 0);
    }
}
