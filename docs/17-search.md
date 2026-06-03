# Search

Sofy ships a zero-dependency, driver-based search engine: an inverted index
you can point at any model, plus an in-memory ranker that powers searchable UI
components. No Elasticsearch, no `ext-intl` — it runs on a bare PHP install and
the same code works on MySQL, PostgreSQL and SQLite.

```php
use Sofy\Search\Search;

Search::query(Product::class, 'red router')->get();   // ranked Product models
```

---

## Quick start (models)

1. Add the trait and declare what to index:

```php
use Sofy\Search\Searchable;

class Product extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return ['sku' => $this->sku, 'name' => $this->name, 'description' => $this->description];
    }
}
```

2. Weight the fields in `config/search.php` (higher = ranked stronger):

```php
'indexes' => [
    \Products\Models\Product::class => ['sku' => 5, 'name' => 3, 'description' => 1],
],
```

3. Migrate (creates the `search_index` table) and import existing rows:

```bash
php sofy migrate
php sofy search:import "Products\Models\Product"
```

From then on the model **auto-indexes on save and drops on delete** — the
`Searchable` trait wires an observer. Search it:

```php
Product::search('rout')->get();        // prefix match → "Router"
Product::search('red router', limit: 10)->get();
```

`search()` returns a `SearchResult` — iterable, countable, hydrates models in
score order:

```php
$result = Product::search('cable');
$result->count();           // how many matched
$result->ids();             // ranked doc ids
foreach ($result as $product) { /* … */ }
```

---

## How ranking works

Text is tokenized (lowercase, accent-folded, stopwords dropped), and each term
is stored with a weight of `field weight × term frequency`. A query sums the
weights of its matching terms per document and orders by that score. The last
word of a query is matched as a **prefix** (autocomplete) when
`tokenizer.prefix` is on, so `"rou"` finds `"router"`.

It's BM25-lite, not Lucene — but it's predictable, portable, and good to tens
of thousands of documents.

### Tokenizer config

```php
'tokenizer' => [
    'lowercase'  => true,
    'unaccent'   => true,      // café → cafe, ё → е
    'min_length' => 2,         // drop 1-char tokens
    'stopwords'  => 'en',      // 'en' | 'ru' | [] | ['your','own','list']
    'prefix'     => true,      // autocomplete on the last token
],
```

---

## Drivers

| Driver | Storage | Use it for |
|--------|---------|-----------|
| `database` (default) | one portable `search_index` table | model search; identical on MySQL/PG/SQLite |
| `collection` | in-memory, per request | tests; tiny transient indexes |

Set with `SEARCH_DRIVER` in `.env`. The database driver needs no
engine-specific full-text DDL — it's just `INSERT`/`GROUP BY`/`SUM`, so it
behaves the same everywhere.

CLI:

```bash
php sofy search:import "App\Models\Post"          # bulk (re)index
php sofy search:import "App\Models\Post" --fresh  # flush first
php sofy search:flush "App\Models\Post"           # clear one index
php sofy search:flush                             # clear everything
```

---

## Searchable UI components

The same engine powers the `combobox` — a searchable `<select>` that stays
usable past a few hundred options (the plain `<select>` doesn't).

### Local (options provided, filtered client-side)

```php
UI::form('/admin/orders', 'POST')
    ->combobox('Product', 'product_id', $options, selected: $current)
    ->submit('Add');

// or standalone:
UI::combobox('product_id', $options, selected: $id);
```

`$options` is `[value => label]` or a list of `['value' => …, 'label' => …]`.
Filtering happens in the browser — great up to a few thousand rows.

### Remote (Search-backed endpoint, for big catalogues)

```php
UI::combobox('product_id', [])->endpoint('/admin/products/search');
```

The combobox fetches `GET /admin/products/search?q=…` as the user types and
expects a JSON list of `{value, label}`. Back it with the engine:

```php
$router->get('/admin/products/search', function (Request $r) {
    $hits = Product::search((string) $r->input('q', ''), limit: 20)->get();
    return json_response(array_map(
        fn($p) => ['value' => $p->id, 'label' => $p->sku . ' — ' . $p->name],
        $hits,
    ));
});
```

That's the whole integration story: the component renders, the route runs
`Model::search()`, the engine ranks. No glue in between.

---

## In-memory ranking (anywhere)

Need to rank an arbitrary collection without an index? `Search::rank()` is what
the combobox uses internally and you can call it directly:

```php
$ordered = Search::rank($products, $typed, fn($p) => $p->sku . ' ' . $p->name);
```

Blank query returns the list unchanged; otherwise best matches first.

---

## What's intentionally simple

- **No stemming** — "running" won't match "run". Prefix matching covers most
  autocomplete needs; add synonyms in `toSearchableArray()` if you need more.
- **Native full-text** (MySQL `FULLTEXT`, Postgres `tsvector`, SQLite FTS5) is
  not used — the portable table wins on identical cross-engine behaviour. A
  native driver can be added later for very large corpora without changing any
  calling code.
