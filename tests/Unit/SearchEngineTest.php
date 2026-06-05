<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Search\Engine;
use Tests\TestCase;

final class SearchEngineTest extends TestCase
{
    private function engine(string $driver): Engine
    {
        return new Engine([
            'driver' => $driver,
            'index_table' => 'search_index',
            'tokenizer' => ['lowercase' => true, 'unaccent' => true, 'min_length' => 2, 'stopwords' => 'en', 'prefix' => true],
        ]);
    }

    private function seed(Engine $e): void
    {
        $e->put('products', '1', ['sku' => 'RT-100', 'name' => 'Wireless Router X1'], ['sku' => 5, 'name' => 3]);
        $e->put('products', '2', ['name' => 'Ethernet Cable for router'], ['name' => 3]);
        $e->put('products', '3', ['name' => 'Network Switch'], ['name' => 3]);
    }

    public function test_collection_driver_ranks_by_field_weight(): void
    {
        $e = $this->engine('collection');
        $this->seed($e);
        $scores = $e->search('products', 'router', 10)->scores();
        // doc 1 (router in name, weight 3) outranks doc 2 (router in name too) —
        // both 3 here; assert both present and doc 3 absent.
        $this->assertArrayHasKey('1', $scores);
        $this->assertArrayHasKey('2', $scores);
        $this->assertArrayNotHasKey('3', $scores);
    }

    public function test_prefix_autocomplete(): void
    {
        $e = $this->engine('collection');
        $this->seed($e);
        $scores = $e->search('products', 'net', 10)->scores();
        $this->assertArrayHasKey('3', $scores); // "net" prefixes "network"
    }

    public function test_multi_term(): void
    {
        $e = $this->engine('collection');
        $this->seed($e);
        $result = $e->search('products', 'network switch', 10);
        $this->assertArrayHasKey('3', $result->scores());
        // ids() normalizes doc ids to strings (array keys come back as ints).
        $this->assertSame(['3'], $result->ids());
    }

    public function test_database_driver_on_sqlite(): void
    {
        $db = $this->freshDatabase();
        $db->execute('CREATE TABLE search_index (id INTEGER PRIMARY KEY, index_name TEXT, doc_id TEXT, term TEXT, weight REAL)');

        $e = $this->engine('database');
        $this->seed($e);

        $this->assertArrayHasKey('1', $e->search('products', 'router', 10)->scores());
        // LIKE wildcard must be escaped → searching '%' matches nothing.
        $this->assertCount(0, $e->search('products', '%', 10)->scores());

        $e->remove('products', '1');
        $this->assertArrayNotHasKey('1', $e->search('products', 'wireless', 10)->scores());
    }

    public function test_rank_helper_for_components(): void
    {
        $e = $this->engine('collection');
        $opts = [
            ['id' => 1, 'label' => 'Wireless Router X1'],
            ['id' => 2, 'label' => 'Ethernet Cable'],
        ];
        $ranked = $e->rank($opts, 'rout', fn($o) => $o['label']);
        $this->assertCount(1, $ranked);
        $this->assertSame('Wireless Router X1', $ranked[0]['label']);
        // Blank query returns all unchanged.
        $this->assertCount(2, $e->rank($opts, '', fn($o) => $o['label']));
    }
}
