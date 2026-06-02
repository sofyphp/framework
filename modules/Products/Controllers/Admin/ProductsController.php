<?php

declare(strict_types=1);

namespace Products\Controllers\Admin;

use Products\Models\Product;
use Sofy\Admin\Admin;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\Icons;
use Sofy\View\UI;

class ProductsController
{
    public function index(Request $request): Response
    {
        $perPage  = (int) config('products.per_page', 25);
        $search   = trim((string) $request->input('q', ''));
        $onlyOff  = (bool) $request->input('inactive', false);

        try {
            $q = Product::query();
            if ($onlyOff) {
                $q->where('active', false);
            }
            if ($search !== '') {
                $like = '%' . $search . '%';
                $column = preg_match('/^[A-Z]+-?\d/', $search) ? 'sku' : 'name';
                $q->where($column, 'LIKE', $like);
            }
            $rows = $q->orderBy('id', 'DESC')->limit($perPage)->get();
        } catch (\Throwable $e) {
            return $this->migrationsMissing($e->getMessage());
        }

        $searchForm = UI::raw(
            '<form method="GET" action="/admin/products" class="sofy-products-search">'
            . '<input type="search" name="q" placeholder="Поиск: SKU или название" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" class="sofy-form-ctrl">'
            . '<label><input type="checkbox" name="inactive" value="1"' . ($onlyOff ? ' checked' : '') . '> только скрытые</label>'
            . '<button type="submit" class="sofy-btn sofy-btn-ghost">Найти</button>'
            . '</form>',
        );

        $body = empty($rows)
            ? UI::emptyState(
                'Товаров пока нет',
                'Создайте первый товар кнопкой «+ Новый товар» справа сверху.',
                action: UI::button('+ Новый товар', '/admin/products/create', 'primary'),
                icon: '◯',
            )
            : UI::dataTable(
                ['SKU', 'Название', 'Цена', 'Остаток', 'Статус', ''],
                $rows,
                [
                    fn(Product $p) => UI::raw(
                        '<a class="sofy-docs-a" href="/admin/products/' . (int) $p->id . '"><code class="sofy-docs-code">'
                        . htmlspecialchars((string) $p->sku, ENT_QUOTES, 'UTF-8')
                        . '</code></a>',
                    ),
                    fn(Product $p) => (string) $p->name,
                    fn(Product $p) => number_format((float) $p->price, 2, '.', ' ') . ' ' . config('products.default_currency', 'USD'),
                    fn(Product $p) => (string) (int) $p->stock,
                    fn(Product $p) => $p->active
                        ? UI::badge('Активен', 'success')
                        : UI::badge('Скрыт',  'default'),
                    fn(Product $p) => UI::raw(
                        '<a class="sofy-btn sofy-btn-ghost sofy-btn-sm" href="/admin/products/' . (int) $p->id . '/edit">'
                        . UI::icon('edit', size: 12) . '</a>',
                    ),
                ],
                perPage: $perPage,
                searchable: false,
            );

        return Admin::page('Товары')
            ->header('Товары (' . count($rows) . ')', UI::button('+ Новый товар', '/admin/products/create', 'primary'))
            ->add($searchForm, UI::card(null, $body), UI::raw($this->styles()))
            ->response();
    }

    public function show(Request $request, int|string $id): Response
    {
        $p = $this->findOrFail((int) $id);
        if ($p instanceof Response) return $p;

        return Admin::page($p->name)
            ->header($p->name, UI::raw(
                '<a class="sofy-btn sofy-btn-ghost" href="/admin/products">← Назад</a> '
                . '<a class="sofy-btn sofy-btn-ghost" href="/admin/products/' . (int) $p->id . '/edit">'
                . UI::icon('edit', size: 13) . ' Редактировать</a>',
            ))
            ->add(
                UI::card('Информация о товаре', UI::kv([
                    'SKU'         => (string) $p->sku,
                    'Название'    => (string) $p->name,
                    'Цена'        => number_format((float) $p->price, 2, '.', ' ') . ' ' . config('products.default_currency', 'USD'),
                    'Остаток'     => (string) (int) $p->stock,
                    'Статус'      => $p->active ? 'Активен' : 'Скрыт',
                    'Описание'    => (string) ($p->description ?? '—'),
                ], layout: 'inline')),
                UI::raw($this->styles()),
            )
            ->response();
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function edit(Request $request, int|string $id): Response
    {
        $p = $this->findOrFail((int) $id);
        if ($p instanceof Response) return $p;
        return $this->renderForm($p);
    }

    public function store(Request $request): Response
    {
        $data = $this->collectInput($request);
        if (is_string($data)) return $this->renderForm(null, $data);

        try {
            if (($data['sku'] ?? '') === '') {
                $data['sku'] = Product::generateSku((string) config('products.sku_prefix', 'SKU-'));
            }
            $p = Product::create($data);
        } catch (\Throwable $e) {
            return $this->renderForm(null, 'Не удалось создать товар: ' . $e->getMessage());
        }

        return Response::redirect('/admin/products/' . (int) $p->id);
    }

    public function update(Request $request, int|string $id): Response
    {
        $p = $this->findOrFail((int) $id);
        if ($p instanceof Response) return $p;

        $data = $this->collectInput($request);
        if (is_string($data)) return $this->renderForm($p, $data);

        try {
            $p->fill($data);
            $p->save();
        } catch (\Throwable $e) {
            return $this->renderForm($p, 'Не удалось сохранить: ' . $e->getMessage());
        }

        return Response::redirect('/admin/products/' . (int) $p->id);
    }

    public function destroy(Request $request, int|string $id): Response
    {
        $p = $this->findOrFail((int) $id);
        if ($p instanceof Response) return $p;

        try {
            $p->delete();
        } catch (\Throwable) {
            // swallow
        }
        return Response::redirect('/admin/products');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function renderForm(?Product $p, ?string $error = null): Response
    {
        $isEdit = $p !== null;
        $title  = $isEdit ? 'Редактирование «' . $p->name . '»' : 'Новый товар';
        $action = $isEdit ? '/admin/products/' . (int) $p->id : '/admin/products';

        $form = UI::form($action, 'POST')
            ->input(label: 'Название', name: 'name', value: $isEdit ? $p->name : '', required: true)
            ->input(label: 'SKU',      name: 'sku',  value: $isEdit ? $p->sku  : '', hint: 'Пустое поле = сгенерировать автоматически')
            ->number(label: 'Цена',    name: 'price', value: $isEdit ? $p->price : '0.00', required: true)
            ->number(label: 'Остаток', name: 'stock', value: $isEdit ? $p->stock : '0')
            ->textarea(label: 'Описание', name: 'description', value: $isEdit ? ($p->description ?? '') : '', rows: 4)
            ->checkbox(label: 'Активен (виден в каталоге)', name: 'active', checked: $isEdit ? (bool) $p->active : true)
            ->submit($isEdit ? 'Сохранить' : 'Создать товар', 'primary');

        return Admin::page($title)
            ->header($title, UI::button('← Назад', $isEdit ? '/admin/products/' . (int) $p->id : '/admin/products', 'ghost'))
            ->add(
                $error !== null ? UI::alert($error, 'danger', 'Не удалось сохранить') : UI::raw(''),
                UI::card(null, $form),
            )
            ->response();
    }

    /** @return array<string,mixed>|string */
    private function collectInput(Request $request): array|string
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') return 'Название обязательно.';

        $price = max(0.0, (float) $request->input('price', 0));
        $stock = max(0,   (int)   $request->input('stock', 0));

        return [
            'sku'         => trim((string) $request->input('sku', '')),
            'name'        => $name,
            'price'       => $price,
            'stock'       => $stock,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'active'      => (bool) $request->input('active', false),
        ];
    }

    private function findOrFail(int $id): Product|Response
    {
        try {
            $p = Product::find($id);
        } catch (\Throwable $e) {
            return $this->migrationsMissing($e->getMessage());
        }
        if ($p === null) {
            return Admin::page('Товар не найден')
                ->header('Товар не найден', UI::button('← Назад', '/admin/products', 'ghost'))
                ->add(UI::alert("Товар #{$id} не существует.", 'warning'))
                ->response();
        }
        return $p;
    }

    private function migrationsMissing(string $detail): Response
    {
        return Admin::page('Товары')
            ->header('Товары')
            ->add(UI::alert(
                UI::raw(
                    'Таблица товаров ещё не создана. Запустите '
                    . '<code class="sofy-docs-code">php sofy migrate</code>.'
                    . '<br><br><small>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</small>',
                ),
                'warning',
                'Нет таблицы products',
            ))
            ->response();
    }

    private function styles(): string
    {
        return <<<CSS
        <style>
            .sofy-products-search{display:flex;gap:10px;align-items:center;margin:0 0 12px;max-width:640px}
            .sofy-products-search .sofy-form-ctrl{flex:1}
            .sofy-products-search label{font-size:12.5px;color:var(--muted);display:inline-flex;gap:4px;align-items:center}
            .sofy-btn-sm{padding:3px 8px;font-size:11px}
        </style>
        CSS;
    }
}
