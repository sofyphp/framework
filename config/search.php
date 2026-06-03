<?php

/*
 | Search engine configuration. See docs/17-search.md.
 |
 | Sofy ships a zero-dependency, driver-based search engine:
 |   - database   : portable inverted index in one table; identical on
 |                  MySQL / PostgreSQL / SQLite. The default.
 |   - collection : ephemeral in-memory index (per request). No DB. Good for
 |                  tests and transient component-backed lists.
 |
 | Per-model indexes declare which fields are searchable and their weight
 | (higher = ranked stronger). A model uses the Sofy\Search\Searchable trait
 | to auto-(re)index on save and drop on delete.
*/

return [
    'driver' => env('SEARCH_DRIVER', 'database'),

    'index_table' => env('SEARCH_INDEX_TABLE', 'search_index'),

    /*
     | Tokenizer — how text is broken into searchable terms.
     |   lowercase   : case-insensitive matching
     |   unaccent    : fold accents (café → cafe, ё → е)
     |   min_length  : drop tokens shorter than this
     |   stopwords   : 'en' | 'ru' | [] | custom list — common words to ignore
     |   prefix      : enable prefix matching on the last query token, so
     |                 "rou" matches "router" (autocomplete)
    */
    'tokenizer' => [
        'lowercase'  => true,
        'unaccent'   => true,
        'min_length' => 2,
        'stopwords'  => env('SEARCH_STOPWORDS', 'en'),
        'prefix'     => true,
    ],

    /*
     | Per-model index definitions: field => weight. If a Searchable model is
     | not listed here, its toSearchableArray() keys are indexed with weight 1.
     |
     |   \Products\Models\Product::class => ['sku' => 5, 'name' => 3, 'description' => 1],
    */
    'indexes' => [
        //
    ],
];
