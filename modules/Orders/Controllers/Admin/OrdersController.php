<?php

declare(strict_types=1);

namespace Orders\Controllers\Admin;

use Orders\Models\Order;
use Orders\Models\OrderItem;
use Sofy\Admin\Admin;
use Sofy\Http\Request;
use Sofy\Http\Response;
use Sofy\View\Icons;
use Sofy\View\UI;

/**
 * Admin CRUD for orders. Listing supports a status filter and free-text
 * search; the detail view shows the order header + its line items, with
 * inline forms to mutate items and a quick status-change buttons row.
 */
class OrdersController
{
    public function index(Request $request): Response
    {
        $statuses = (array) config('orders.statuses');
        $perPage  = (int)   config('orders.per_page', 25);
        $variants = (array) config('orders.status_variants');

        $status = trim((string) $request->input('status', ''));
        $search = trim((string) $request->input('q', ''));

        try {
            $q = Order::query();
            if ($status !== '' && isset($statuses[$status])) {
                $q->where('status', $status);
            }
            if ($search !== '') {
                // Sofy's eloquent-flavoured Builder doesn't take a closure
                // for grouped WHERE clauses, so we keep search simple: match
                // on a single likely field at a time. Number is the most
                // common admin lookup; fall back to name/email when the
                // search term doesn't look like an order number.
                $like = '%' . $search . '%';
                $column = preg_match('/^[\w\-]+\d/', $search) === 1
                    ? 'number'
                    : (str_contains($search, '@') ? 'customer_email' : 'customer_name');
                $q->where($column, 'LIKE', $like);
            }
            $orders = $q->orderBy('id', 'DESC')->limit($perPage)->get();
        } catch (\Throwable $e) {
            return $this->migrationsMissing($e->getMessage());
        }

        // Filter chips — render via raw HTML for tighter control than UI::badge.
        $chips = '<div class="sofy-orders-filters">'
            . $this->statusChip('', 'Все', $status === '', $variants)
            . implode('', array_map(
                fn(string $k) => $this->statusChip($k, $statuses[$k], $status === $k, $variants),
                array_keys($statuses),
            ))
            . '</div>';

        $searchForm = UI::raw(
            '<form method="GET" action="/admin/orders" class="sofy-orders-search">'
            . ($status !== '' ? '<input type="hidden" name="status" value="' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<input type="search" name="q" placeholder="Поиск: номер, имя или email" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" class="sofy-form-ctrl">'
            . '<button type="submit" class="sofy-btn sofy-btn-ghost">Найти</button>'
            . '</form>',
        );

        $body = empty($orders)
            ? UI::emptyState(
                $search !== '' || $status !== '' ? 'Ничего не найдено' : 'Заказов пока нет',
                $search !== '' || $status !== ''
                    ? 'Попробуйте сбросить фильтры или поиск.'
                    : 'Создайте первый заказ кнопкой «+ Новый заказ» справа сверху.',
                action: UI::button('+ Новый заказ', '/admin/orders/create', 'primary'),
                icon: '◯',
            )
            : UI::dataTable(
                ['#', 'Клиент', 'Статус', 'Сумма', 'Создан', ''],
                $orders,
                [
                    fn(Order $o) => UI::raw(
                        '<a class="sofy-docs-a" href="/admin/orders/' . (int) $o->id . '">'
                        . htmlspecialchars((string) $o->number, ENT_QUOTES, 'UTF-8') . '</a>',
                    ),
                    fn(Order $o) => UI::raw(
                        '<div><strong>' . htmlspecialchars((string) $o->customer_name, ENT_QUOTES, 'UTF-8') . '</strong></div>'
                        . '<div class="sofy-muted">' . htmlspecialchars((string) $o->customer_email, ENT_QUOTES, 'UTF-8') . '</div>',
                    ),
                    fn(Order $o) => UI::badge(
                        (string) ($statuses[$o->status] ?? $o->status),
                        (string) ($variants[$o->status] ?? 'default'),
                    ),
                    fn(Order $o) => $this->formatMoney((float) $o->total, (string) $o->currency),
                    fn(Order $o) => $this->formatDate($o->created_at ?? null),
                    fn(Order $o) => UI::raw(
                        '<a class="sofy-btn sofy-btn-ghost sofy-btn-sm" href="/admin/orders/' . (int) $o->id . '">Открыть</a>',
                    ),
                ],
                perPage: $perPage,
                searchable: false,
            );

        return Admin::page('Заказы')
            ->header('Заказы (' . count($orders) . ')', UI::button('+ Новый заказ', '/admin/orders/create', 'primary'))
            ->add(
                UI::raw($chips),
                $searchForm,
                UI::card(null, $body),
                UI::raw($this->styles()),
            )
            ->response();
    }

    public function show(Request $request, int|string $id): Response
    {
        $order = $this->findOrFail((int) $id);
        if ($order instanceof Response) return $order;

        $statuses = (array) config('orders.statuses');
        $variants = (array) config('orders.status_variants');
        $items    = $order->items()->get();

        $csrf = function_exists('csrf_token') ? csrf_token() : '';

        // AdminPage::header() takes a string title, so we render the
        // number plain-text and emit the status badge as a separate
        // first-row component just below the page header.
        $headerTitle = 'Заказ ' . $order->number;
        $statusBadge = UI::raw(
            '<div class="sofy-orders-status-line">'
            . UI::badge(
                (string) ($statuses[$order->status] ?? $order->status),
                (string) ($variants[$order->status] ?? 'default'),
            )
            . '</div>',
        );

        // Order summary card.
        $summary = UI::card('Информация о заказе', UI::kv([
            'Номер'        => (string) $order->number,
            'Клиент'       => (string) $order->customer_name,
            'Email'        => (string) $order->customer_email,
            'Сумма'        => $this->formatMoney((float) $order->total, (string) $order->currency),
            'Статус'       => (string) ($statuses[$order->status] ?? $order->status),
            'Создан'       => $this->formatDate($order->created_at ?? null),
            'Обновлён'     => $this->formatDate($order->updated_at ?? null),
            'Комментарий'  => (string) ($order->notes ?? '—'),
        ], layout: 'inline'));

        // Status quick-change row.
        $statusButtons = '<div class="sofy-orders-status-row">';
        foreach ($statuses as $key => $label) {
            $statusButtons .= '<form method="POST" action="/admin/orders/' . (int) $order->id . '/status">'
                . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
                . '<input type="hidden" name="status" value="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '">'
                . '<button type="submit" class="sofy-btn sofy-btn-' . htmlspecialchars((string) ($variants[$key] ?? 'ghost'), ENT_QUOTES, 'UTF-8')
                . ($order->status === $key ? ' sofy-btn-active" disabled' : '"')
                . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>'
                . '</form>';
        }
        $statusButtons .= '</div>';

        // Items table.
        if (empty($items)) {
            $itemsCard = UI::card('Позиции', UI::emptyState('Позиций ещё нет', 'Добавьте первую позицию формой ниже.', icon: '◯'));
        } else {
            $itemsCard = UI::card(
                'Позиции (' . count($items) . ')',
                UI::table(
                    ['Название', 'Кол-во', 'Цена', 'Сумма', ''],
                    $items,
                    [
                        fn(OrderItem $i) => (string) $i->name,
                        fn(OrderItem $i) => (string) $i->quantity,
                        fn(OrderItem $i) => $this->formatMoney((float) $i->unit_price, (string) $order->currency),
                        fn(OrderItem $i) => $this->formatMoney((float) $i->total,      (string) $order->currency),
                        fn(OrderItem $i) => UI::raw(
                            '<form method="POST" action="/admin/orders/' . (int) $order->id . '" style="display:inline">'
                            . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
                            . '<input type="hidden" name="_action" value="delete_item">'
                            . '<input type="hidden" name="item_id" value="' . (int) $i->id . '">'
                            . '<button class="sofy-btn sofy-btn-ghost sofy-btn-sm" type="submit" onclick="return confirm(\'Удалить позицию?\');">'
                            . UI::icon('trash', size: 13) . '</button>'
                            . '</form>',
                        ),
                    ],
                ),
            );
        }

        // Add-item form. Two forms side-by-side when the Products module is
        // installed — free-form (existing) + from-catalog dropdown.
        $freeForm =
            '<form method="POST" action="/admin/orders/' . (int) $order->id . '" class="sofy-orders-additem">'
            . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
            . '<input type="hidden" name="_action" value="add_item">'
            . '<input class="sofy-form-ctrl" name="item_name"  required placeholder="Название позиции">'
            . '<input class="sofy-form-ctrl" name="item_qty"   required type="number" min="1" step="1" value="1" style="max-width:90px">'
            . '<input class="sofy-form-ctrl" name="item_price" required type="number" min="0" step="0.01" placeholder="Цена" style="max-width:140px">'
            . '<button class="sofy-btn sofy-btn-primary" type="submit">+ Добавить</button>'
            . '</form>';

        // Catalog form is conditional — Products module may not be installed
        // and we don't want to hard-depend on it. Check class_exists guards
        // against a stale autoloader; the try/catch around Product::query()
        // covers a missing products table.
        $catalogForm = '';
        if (class_exists(\Products\Models\Product::class)) {
            try {
                $products = \Products\Models\Product::query()->where('active', true)->orderBy('name')->limit(500)->get();
            } catch (\Throwable) {
                $products = [];
            }
            if (!empty($products)) {
                $opts = '';
                foreach ($products as $p) {
                    /** @var \Products\Models\Product $p */
                    $opts .= '<option value="' . (int) $p->id . '" data-price="' . htmlspecialchars((string) $p->price, ENT_QUOTES, 'UTF-8') . '">'
                        . htmlspecialchars((string) $p->sku, ENT_QUOTES, 'UTF-8') . ' — '
                        . htmlspecialchars((string) $p->name, ENT_QUOTES, 'UTF-8') . ' · '
                        . number_format((float) $p->price, 2, '.', ' ') . ' ' . (string) $order->currency
                        . '</option>';
                }
                $catalogForm =
                    '<form method="POST" action="/admin/orders/' . (int) $order->id . '" class="sofy-orders-additem">'
                    . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
                    . '<input type="hidden" name="_action" value="add_from_catalog">'
                    . '<select class="sofy-form-ctrl" name="product_id" required style="flex:1">'
                    . '<option value="">— выберите товар из каталога —</option>' . $opts
                    . '</select>'
                    . '<input class="sofy-form-ctrl" name="item_qty" required type="number" min="1" step="1" value="1" style="max-width:90px">'
                    . '<button class="sofy-btn sofy-btn-primary" type="submit">+ Из каталога</button>'
                    . '</form>';
            }
        }

        $addItem = UI::card('Добавить позицию', UI::raw(
            ($catalogForm !== ''
                ? '<div class="sofy-orders-add-label">Из каталога:</div>' . $catalogForm
                  . '<div class="sofy-orders-add-label">Свободная позиция:</div>'
                : '')
            . $freeForm,
        ));

        return Admin::page($headerTitle)
            ->header(
                $headerTitle,
                UI::raw(
                    '<a class="sofy-btn sofy-btn-ghost" href="/admin/orders">← Назад</a> '
                    . '<a class="sofy-btn sofy-btn-ghost" href="/admin/orders/' . (int) $order->id . '/edit">'
                    . UI::icon('edit', size: 13) . ' Редактировать</a> '
                    . '<form method="POST" action="/admin/orders/' . (int) $order->id . '/delete" '
                    . 'onsubmit="return confirm(\'Удалить заказ полностью? Действие необратимо.\');" style="display:inline">'
                    . ($csrf !== '' ? '<input type="hidden" name="_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">' : '')
                    . '<button class="sofy-btn sofy-btn-danger" type="submit">' . UI::icon('trash', size: 13) . ' Удалить</button>'
                    . '</form>',
                ),
            )
            ->add(
                $statusBadge,
                UI::card('Сменить статус', UI::raw($statusButtons)),
                $summary,
                $itemsCard,
                $addItem,
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
        $order = $this->findOrFail((int) $id);
        if ($order instanceof Response) return $order;
        return $this->renderForm($order);
    }

    public function store(Request $request): Response
    {
        $data = $this->collectOrderInput($request);
        if (is_string($data)) {
            return $this->renderForm(null, $data);
        }

        try {
            $order = Order::create($data + ['number' => Order::generateNumber((string) config('orders.number_prefix', 'ORD-'))]);
        } catch (\Throwable $e) {
            return $this->renderForm(null, 'Не удалось создать заказ: ' . $e->getMessage());
        }

        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    public function update(Request $request, int|string $id): Response
    {
        $order = $this->findOrFail((int) $id);
        if ($order instanceof Response) return $order;

        // The show() page also POSTs to this URL for inline item add/delete.
        $action = (string) $request->input('_action', '');
        if ($action === 'add_item')         return $this->addItem($request, $order);
        if ($action === 'add_from_catalog') return $this->addFromCatalog($request, $order);
        if ($action === 'delete_item')      return $this->deleteItem($request, $order);

        $data = $this->collectOrderInput($request);
        if (is_string($data)) {
            return $this->renderForm($order, $data);
        }

        try {
            $order->fill($data);
            $order->save();
        } catch (\Throwable $e) {
            return $this->renderForm($order, 'Не удалось сохранить: ' . $e->getMessage());
        }

        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    public function updateStatus(Request $request, int|string $id): Response
    {
        $order = $this->findOrFail((int) $id);
        if ($order instanceof Response) return $order;

        $status = (string) $request->input('status', '');
        if (!in_array($status, Order::STATUSES, true)) {
            return Response::redirect('/admin/orders/' . (int) $order->id);
        }

        $order->status = $status;
        $order->save();

        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    public function destroy(Request $request, int|string $id): Response
    {
        $order = $this->findOrFail((int) $id);
        if ($order instanceof Response) return $order;

        try {
            // Delete child items first — schema has no FK cascade.
            foreach ($order->items()->get() as $item) {
                /** @var OrderItem $item */
                $item->delete();
            }
            $order->delete();
        } catch (\Throwable) {
            // Swallow — show() will already 404 if it was partially gone.
        }

        return Response::redirect('/admin/orders');
    }

    // ── Inline item mutations from the order detail page ────────────────────

    private function addItem(Request $request, Order $order): Response
    {
        $name  = trim((string) $request->input('item_name', ''));
        $qty   = max(1,   (int)   $request->input('item_qty',   1));
        $price = max(0.0, (float) $request->input('item_price', 0));

        if ($name === '') {
            return Response::redirect('/admin/orders/' . (int) $order->id);
        }

        OrderItem::create([
            'order_id'   => (int) $order->id,
            'name'       => $name,
            'quantity'   => $qty,
            'unit_price' => $price,
            'total'      => round($qty * $price, 2),
        ]);

        $order->recalcTotal();

        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    /**
     * Add a line item sourced from the Products catalogue. Soft-depends
     * on the Products module — class_exists() guards against a stale
     * install. The order item gets a snapshot of the product's current
     * name + price + a link via product_id so historical totals stay
     * stable even if the catalogue changes later.
     */
    private function addFromCatalog(Request $request, Order $order): Response
    {
        $productId = (int) $request->input('product_id', 0);
        $qty       = max(1, (int) $request->input('item_qty', 1));

        if ($productId <= 0 || !class_exists(\Products\Models\Product::class)) {
            return Response::redirect('/admin/orders/' . (int) $order->id);
        }

        try {
            $product = \Products\Models\Product::find($productId);
        } catch (\Throwable) {
            $product = null;
        }
        if ($product === null) {
            return Response::redirect('/admin/orders/' . (int) $order->id);
        }

        $unitPrice = (float) $product->price;
        OrderItem::create([
            'order_id'   => (int) $order->id,
            'product_id' => (int) $product->id,
            'name'       => (string) $product->name,
            'quantity'   => $qty,
            'unit_price' => $unitPrice,
            'total'      => round($qty * $unitPrice, 2),
        ]);

        $order->recalcTotal();
        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    private function deleteItem(Request $request, Order $order): Response
    {
        $itemId = (int) $request->input('item_id', 0);
        if ($itemId > 0) {
            $item = OrderItem::find($itemId);
            if ($item !== null && (int) $item->order_id === (int) $order->id) {
                $item->delete();
                $order->recalcTotal();
            }
        }
        return Response::redirect('/admin/orders/' . (int) $order->id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string,mixed>|string  field array on success, error message on failure. */
    private function collectOrderInput(Request $request): array|string
    {
        $statuses = (array) config('orders.statuses');

        $name  = trim((string) $request->input('customer_name', ''));
        $email = trim((string) $request->input('customer_email', ''));
        if ($name === '' || $email === '') {
            return 'Имя и email клиента обязательны.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email клиента невалидный.';
        }

        $status = (string) $request->input('status', Order::STATUS_PENDING);
        if (!isset($statuses[$status])) {
            $status = Order::STATUS_PENDING;
        }

        $currency = strtoupper(trim((string) $request->input('currency', (string) config('orders.default_currency', 'USD'))));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = (string) config('orders.default_currency', 'USD');
        }

        return [
            'customer_name'  => $name,
            'customer_email' => $email,
            'status'         => $status,
            'currency'       => $currency,
            'notes'          => trim((string) $request->input('notes', '')) ?: null,
        ];
    }

    private function renderForm(?Order $order, ?string $error = null): Response
    {
        $statuses = (array) config('orders.statuses');
        $isEdit   = $order !== null;
        $title    = $isEdit ? 'Редактирование ' . $order->number : 'Новый заказ';
        $action   = $isEdit ? '/admin/orders/' . (int) $order->id : '/admin/orders';

        $form = UI::form($action, 'POST')
            ->input(
                label: 'Имя клиента',
                name:  'customer_name',
                value: $isEdit ? $order->customer_name : '',
                required: true,
            )
            ->email(
                label: 'Email клиента',
                name:  'customer_email',
                value: $isEdit ? $order->customer_email : '',
                required: true,
            )
            ->select(
                label:    'Статус',
                name:     'status',
                options:  $statuses,
                selected: $isEdit ? $order->status : Order::STATUS_PENDING,
            )
            ->input(
                label: 'Валюта',
                name:  'currency',
                value: $isEdit ? $order->currency : (string) config('orders.default_currency', 'USD'),
                hint:  'ISO-код, 3 буквы',
            )
            ->textarea(
                label: 'Комментарий',
                name:  'notes',
                value: $isEdit ? ($order->notes ?? '') : '',
                rows:  4,
            )
            ->submit($isEdit ? 'Сохранить' : 'Создать заказ', 'primary');

        return Admin::page($title)
            ->header($title, UI::button('← Назад', $isEdit ? '/admin/orders/' . (int) $order->id : '/admin/orders', 'ghost'))
            ->add(
                $error !== null ? UI::alert($error, 'danger', 'Не удалось сохранить') : UI::raw(''),
                UI::card(null, $form),
            )
            ->response();
    }

    private function findOrFail(int $id): Order|Response
    {
        try {
            $order = Order::find($id);
        } catch (\Throwable $e) {
            return $this->migrationsMissing($e->getMessage());
        }
        if ($order === null) {
            return Admin::page('Заказ не найден')
                ->header('Заказ не найден', UI::button('← Назад', '/admin/orders', 'ghost'))
                ->add(UI::alert("Заказ #{$id} не существует или был удалён.", 'warning'))
                ->response();
        }
        return $order;
    }

    private function migrationsMissing(string $detail): Response
    {
        return Admin::page('Заказы')
            ->header('Заказы')
            ->add(UI::alert(
                UI::raw(
                    'Таблицы заказов ещё не созданы. Запустите '
                    . '<code class="sofy-docs-code">php sofy migrate</code> — модуль Orders подкинет '
                    . 'свои миграции автоматически.<br><br><small>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</small>',
                ),
                'warning',
                'Нет таблиц orders / order_items',
            ))
            ->response();
    }

    private function statusChip(string $value, string $label, bool $active, array $variants): string
    {
        $href = '/admin/orders' . ($value !== '' ? '?status=' . rawurlencode($value) : '');
        $cls  = 'sofy-orders-chip' . ($active ? ' sofy-orders-chip-active' : '');
        $variant = $variants[$value] ?? 'default';
        return '<a class="' . $cls . ' sofy-orders-chip-' . htmlspecialchars($variant, ENT_QUOTES, 'UTF-8') . '" href="' . $href . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', ' ') . ' ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
    }

    private function formatDate(mixed $raw): string
    {
        if ($raw === null || $raw === '') return '—';
        try { return (new \DateTimeImmutable((string) $raw))->format('Y-m-d H:i'); }
        catch (\Throwable) { return (string) $raw; }
    }

    private function styles(): string
    {
        return <<<CSS
        <style>
            .sofy-orders-filters{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px}
            .sofy-orders-chip{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:12px;font-weight:600;text-decoration:none;transition:border-color .12s ease}
            .sofy-orders-chip:hover{border-color:var(--accent)}
            .sofy-orders-chip-active{border-color:var(--accent);background:var(--accent-soft,rgba(255,107,90,.12));color:var(--accent)}
            .sofy-orders-search{display:flex;gap:8px;align-items:center;margin:0 0 12px;max-width:520px}
            .sofy-orders-search .sofy-form-ctrl{flex:1}
            .sofy-orders-status-row{display:flex;flex-wrap:wrap;gap:6px}
            .sofy-orders-status-row form{margin:0}
            .sofy-btn-sm{padding:3px 8px;font-size:11px}
            .sofy-btn-active{opacity:.55;cursor:default}
            .sofy-orders-additem{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
            .sofy-orders-additem .sofy-form-ctrl{flex:1;min-width:160px}
            .sofy-orders-additem{margin:6px 0 12px}
            .sofy-orders-add-label{font-size:11px;font-weight:700;color:var(--muted);letter-spacing:.06em;text-transform:uppercase;margin:8px 0 4px}
            .sofy-orders-status-line{margin:0 0 12px}
            .sofy-muted{color:var(--muted);font-size:12px}
        </style>
        CSS;
    }
}
