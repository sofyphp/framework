<?php

declare(strict_types=1);

namespace Sofy\Search\Drivers;

use Sofy\Database\Connection;

/**
 * Portable inverted index in a single table. Works identically on MySQL,
 * PostgreSQL and SQLite — no engine-specific full-text DDL — by storing one
 * row per (index, document, term) with a pre-computed weight, then ranking
 * with a plain GROUP BY / SUM. Native FULLTEXT can be a separate driver later
 * for very large corpora; this one is zero-config and good to tens of
 * thousands of documents.
 *
 * Schema (see the create_search_index_table migration):
 *   index_name  VARCHAR   the index (usually a model class)
 *   doc_id      VARCHAR   the document key
 *   term        VARCHAR   a normalized token
 *   weight      FLOAT     field weight × term frequency
 */
final class DatabaseDriver implements DriverInterface
{
    public function __construct(
        private readonly Connection $conn,
        private readonly string $table = 'search_index',
    ) {}

    public function put(string $index, string $docId, array $terms): void
    {
        $this->remove($index, $docId);
        if ($terms === []) {
            return;
        }

        // Batch insert all terms for this document in one statement.
        $rows = [];
        $bind = [];
        foreach ($terms as $term => $weight) {
            $rows[] = '(?, ?, ?, ?)';
            $bind[] = $index;
            $bind[] = $docId;
            $bind[] = (string) $term;
            $bind[] = (float) $weight;
        }

        $sql = "INSERT INTO {$this->q($this->table)} (index_name, doc_id, term, weight) VALUES "
            . implode(', ', $rows);
        $this->conn->execute($sql, $bind);
    }

    public function remove(string $index, string $docId): void
    {
        $this->conn->execute(
            "DELETE FROM {$this->q($this->table)} WHERE index_name = ? AND doc_id = ?",
            [$index, $docId],
        );
    }

    public function flush(?string $index = null): void
    {
        if ($index === null) {
            $this->conn->execute("DELETE FROM {$this->q($this->table)}");
        } else {
            $this->conn->execute("DELETE FROM {$this->q($this->table)} WHERE index_name = ?", [$index]);
        }
    }

    public function query(string $index, array $terms, ?string $prefix, int $limit, int $offset): array
    {
        $where   = ['index_name = ?'];
        $bind    = [$index];
        $matches = [];

        if ($terms !== []) {
            $matches[] = 'term IN (' . implode(', ', array_fill(0, count($terms), '?')) . ')';
            foreach ($terms as $t) {
                $bind[] = $t;
            }
        }
        if ($prefix !== null && $prefix !== '') {
            $matches[] = 'term LIKE ?';
            $bind[] = $this->escapeLike($prefix) . '%';
        }
        if ($matches === []) {
            return [];
        }
        $where[] = '(' . implode(' OR ', $matches) . ')';

        $limit  = max(1, $limit);
        $offset = max(0, $offset);

        $sql = "SELECT doc_id, SUM(weight) AS score FROM {$this->q($this->table)} "
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'GROUP BY doc_id ORDER BY score DESC '
            . "LIMIT {$limit} OFFSET {$offset}";

        $rows   = $this->conn->query($sql, $bind);
        $scores = [];
        foreach ($rows as $r) {
            $scores[(string) $r['doc_id']] = (float) $r['score'];
        }
        return $scores;
    }

    /** Quote an identifier conservatively for the three supported drivers. */
    private function q(string $id): string
    {
        return $this->conn->getDriverName() === 'mysql'
            ? '`' . str_replace('`', '``', $id) . '`'
            : '"' . str_replace('"', '""', $id) . '"';
    }

    /** Escape LIKE wildcards so a user typing % or _ doesn't broaden the match. */
    private function escapeLike(string $v): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $v);
    }
}
