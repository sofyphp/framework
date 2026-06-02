<?php

declare(strict_types=1);

namespace Sofy\View;

use Sofy\View\UI\Accordion;
use Sofy\View\UI\Banner;
use Sofy\View\UI\Chart;
use Sofy\View\UI\CommandPalette;
use Sofy\View\UI\CopyButton;
use Sofy\View\UI\DataTable;
use Sofy\View\UI\DebugBar;
use Sofy\View\UI\Drawer;
use Sofy\View\UI\Modal;
use Sofy\View\UI\ScrollArea;
use Sofy\View\UI\SidebarLayout;
use Sofy\View\UI\Tag;
use Sofy\View\UI\Toast;
use Sofy\View\UI\Tooltip;
use Sofy\View\UI\Alert;
use Sofy\View\UI\Avatar;
use Sofy\View\UI\AvatarGroup;
use Sofy\View\UI\Badge;
use Sofy\View\UI\Breadcrumb;
use Sofy\View\UI\Button;
use Sofy\View\UI\Card;
use Sofy\View\UI\Code;
use Sofy\View\UI\Divider;
use Sofy\View\UI\EmptyState;
use Sofy\View\UI\Form;
use Sofy\View\UI\Grid;
use Sofy\View\UI\Heading;
use Sofy\View\UI\Hero;
use Sofy\View\UI\Icon;
use Sofy\View\UI\KeyValue;
use Sofy\View\UI\NavBar;
use Sofy\View\UI\OrderedList;
use Sofy\View\UI\Page;
use Sofy\View\UI\Pagination;
use Sofy\View\UI\Progress;
use Sofy\View\UI\RawHtml;
use Sofy\View\UI\Spinner;
use Sofy\View\UI\Stat;
use Sofy\View\UI\Steps;
use Sofy\View\UI\SuccessCheck;
use Sofy\View\UI\Table;
use Sofy\View\UI\Tabs;
use Sofy\View\UI\Text;
use Sofy\View\UI\Timeline;
use Sofy\View\UI\UnorderedList;

/**
 * UI — static factory for all Sofy UI components.
 *
 * Typical usage in a controller:
 *
 *   return UI::page('Users')
 *       ->nav('MyApp', ['/dashboard' => 'Dashboard', '/users' => 'Users'])
 *       ->header('All Users', UI::button('+ New', '/users/create', 'primary'))
 *       ->add(
 *           UI::grid(3, [
 *               UI::stat('Total',  1240, '+5%'),
 *               UI::stat('Active',  983, '+2%'),
 *               UI::stat('Banned',   12, '-1%'),
 *           ]),
 *           UI::card('User list',
 *               UI::table(
 *                   ['ID', 'Name', 'Email', 'Status', ''],
 *                   $users,
 *                   ['id', 'name', 'email',
 *                       fn($r) => UI::badge($r['status'], $r['status'] === 'active' ? 'success' : 'danger'),
 *                       fn($r) => UI::button('Edit', "/users/{$r['id']}/edit", 'ghost', 'sm'),
 *                   ]
 *               )
 *           ),
 *       )
 *       ->response();
 */
class UI
{
    // ── Page ──────────────────────────────────────────────────────────────────

    public static function page(string $title): Page
    {
        return new Page($title);
    }

    // ── Layout ────────────────────────────────────────────────────────────────

    /**
     * @param mixed $content  string | Component | Component[]
     */
    public static function card(?string $title = null, mixed $content = null): Card
    {
        return new Card($title, $content);
    }

    /**
     * @param mixed[] $items
     */
    public static function grid(int $cols, array $items = []): Grid
    {
        return Grid::make($cols, $items);
    }

    public static function tabs(array $tabs, int $default = 0): Tabs
    {
        return new Tabs($tabs, $default);
    }

    public static function navbar(string $brand, array $links = []): NavBar
    {
        return new NavBar($brand, '/', $links);
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    /**
     * @param string[]                    $headers  Column labels
     * @param array                       $rows     Rows (arrays or objects with toArray())
     * @param array<string|callable>|null $cols     Keys or closures; null = auto-detect
     * @param string                      $empty    Message when no rows
     */
    public static function table(
        array   $headers,
        array   $rows,
        ?array  $cols  = null,
        string  $empty = 'No records found.',
    ): Table {
        return new Table($headers, $rows, $cols, $empty);
    }

    public static function stat(string $label, mixed $value, ?string $trend = null, ?string $description = null): Stat
    {
        return new Stat($label, $value, $trend, $description);
    }

    // ── Forms ─────────────────────────────────────────────────────────────────

    /**
     * Returns a Form builder.
     *
     *   UI::form('/users', 'POST')
     *       ->input('Name', 'name', required: true)
     *       ->email('Email', 'email', required: true)
     *       ->select('Role', 'role', ['admin' => 'Admin', 'user' => 'User'])
     *       ->textarea('Bio', 'bio', rows: 5)
     *       ->checkbox('Active', 'is_active', checked: true)
     *       ->submit('Save')
     */
    public static function form(string $action, string $method = 'POST'): Form
    {
        return new Form($action, $method);
    }

    // ── Feedback ──────────────────────────────────────────────────────────────

    /**
     * @param string|UI\Component             $message Plain string is escaped; pass UI::raw('<…>') when you need
     *                                                 inline markup like <code> or <a> in the message.
     * @param 'success'|'warning'|'danger'|'info' $type
     * @param string|UI\Component|null        $title
     */
    public static function alert(string|UI\Component $message, string $type = 'info', string|UI\Component|null $title = null): Alert
    {
        return new Alert($message, $type, $title);
    }

    // ── Navigation / Actions ──────────────────────────────────────────────────

    /**
     * @param 'primary'|'ghost'|'warning'|'danger'|'success' $variant
     * @param 'sm'|'md'|'lg'                        $size
     */
    public static function button(
        string  $label,
        string  $href    = '#',
        string  $variant = 'ghost',
        string  $size    = 'md',
        ?string $method  = null,
        ?string $confirm = null,
    ): Button {
        return new Button($label, $href, $variant, $size, $method, $confirm);
    }

    /** Shortcut: a danger button that submits a DELETE form with confirmation. */
    public static function deleteButton(string $href, string $label = 'Delete', ?string $confirm = 'Are you sure?'): Button
    {
        return new Button($label, $href, 'danger', 'sm', 'DELETE', $confirm);
    }

    /** @param 'default'|'success'|'warning'|'danger'|'info'|'accent' $variant */
    public static function badge(string $label, string $variant = 'default'): Badge
    {
        return new Badge($label, $variant);
    }

    // ── Hero ──────────────────────────────────────────────────────────────────

    public static function hero(string $title, string $subtitle = ''): Hero
    {
        return new Hero($title, $subtitle);
    }

    /**
     * Render a named SVG icon from \Sofy\View\Icons. Accepts kebab-case
     * ('user-plus'), snake_case ('user_plus') or upper ('USER_PLUS').
     *
     *   UI::icon('home')                          // 16px, inherits color
     *   UI::icon('alert-triangle', size: 20)
     *   UI::icon('check-circle', color: 'var(--success)', strokeWidth: 2)
     *
     * Unknown names render as an empty marker span (data-name set) so
     * typos are obvious in DevTools without breaking the layout.
     */
    public static function icon(string $name, int $size = 16, string $color = 'currentColor', int $strokeWidth = 2): Icon
    {
        return new Icon($name, $size, $color, $strokeWidth);
    }

    // ── Content ───────────────────────────────────────────────────────────────

    public static function heading(string $text, int $level = 2): Heading
    {
        return new Heading($text, $level);
    }

    public static function text(string $content, bool $muted = false): Text
    {
        return new Text($content, $muted);
    }

    public static function code(string $code, string $language = ''): Code
    {
        return new Code($code, $language);
    }

    public static function divider(): Divider
    {
        return new Divider();
    }

    public static function ul(array $items): UnorderedList
    {
        return new UnorderedList($items);
    }

    public static function ol(array $items): OrderedList
    {
        return new OrderedList($items);
    }

    /** Inject raw HTML — use only with trusted content. */
    public static function raw(string $html): RawHtml
    {
        return new RawHtml($html);
    }

    // ── Navigation ────────────────────────────────────────────────────────────

    /**
     * Breadcrumb trail.
     * Pass ['Label' => '/url', 'Current' => null] — null = no link (current page).
     *
     * @param array<string, string|null> $items
     */
    public static function breadcrumb(array $items): Breadcrumb
    {
        return new Breadcrumb($items);
    }

    /**
     * Page number navigation.
     *
     * @param string $baseUrl  Page number is appended, e.g. '?page='
     */
    public static function pagination(int $current, int $total, string $baseUrl = '?page=', int $window = 2): Pagination
    {
        return new Pagination($current, $total, $baseUrl, $window);
    }

    /**
     * Wizard / process step indicator.
     *
     * @param string[] $steps
     * @param int      $current  1-based active step index
     */
    public static function steps(array $steps, int $current = 1): Steps
    {
        return new Steps($steps, $current);
    }

    // ── Data display ──────────────────────────────────────────────────────────

    /**
     * Horizontal progress bar.
     *
     * @param string $variant  accent|success|warning|danger|info
     * @param string $size     sm|md|lg
     */
    public static function progress(int $value, ?string $label = null, string $variant = 'accent', string $size = 'md', bool $showPct = true): Progress
    {
        return new Progress($value, $label, $variant, $size, $showPct);
    }

    /**
     * Key → value description list.
     *
     * @param array<string, mixed> $items   ['Label' => 'value', ...] — value may be a Component
     * @param 'inline'|'stacked'   $layout
     */
    public static function kv(array $items, string $layout = 'inline'): KeyValue
    {
        return new KeyValue($items, $layout);
    }

    /**
     * Collapsible accordion panels (uses native <details>/<summary> — no JS).
     *
     * @param array<array{title: string, content: string|\Sofy\View\UI\Component, open?: bool}> $items
     */
    public static function accordion(array $items): Accordion
    {
        return new Accordion($items);
    }

    /**
     * Empty-state placeholder with optional CTA.
     */
    public static function emptyState(string $title, string $description = '', mixed $action = null, string $icon = '◯'): EmptyState
    {
        return new EmptyState($title, $description, $action, $icon);
    }

    /**
     * Activity / event timeline.
     *
     * @param array<array{title: string, time?: string, content?: string|\Sofy\View\UI\Component, variant?: string}> $items
     */
    public static function timeline(array $items): Timeline
    {
        return new Timeline($items);
    }

    // ── Misc ──────────────────────────────────────────────────────────────────

    /**
     * User avatar — shows initials or an image.
     *
     * @param string  $variant  accent|success|warning|danger|info|muted
     * @param string  $size     sm|md|lg|xl
     * @param string|null $src  Image URL; overrides initials
     */
    public static function avatar(string $name, string $variant = 'accent', string $size = 'md', ?string $src = null): Avatar
    {
        return new Avatar($name, $variant, $size, $src);
    }

    /**
     * Avatar / chip group with a distance-falloff hover spring
     * (transitions.dev). Hover an item to lift it and its neighbours.
     *
     * @param array<mixed> $items  Avatar components (or any renderable items)
     */
    public static function avatarGroup(array $items): AvatarGroup
    {
        return new AvatarGroup($items);
    }

    /**
     * Animated success checkmark — fades in, rotates upright, Y-bobs and
     * stroke-draws its path (transitions.dev). Plays on load when $autoplay,
     * otherwise trigger from JS with sofyShowCheck(el).
     */
    public static function successCheck(bool $autoplay = true, ?string $id = null): SuccessCheck
    {
        return new SuccessCheck($autoplay, $id);
    }

    /**
     * CSS-animated loading spinner.
     *
     * @param string $size    sm|md|lg
     * @param string $variant accent|muted|white
     */
    public static function spinner(string $size = 'md', string $variant = 'accent'): Spinner
    {
        return new Spinner($size, $variant);
    }

    /**
     * Scrollable container with a styled thin scrollbar.
     *
     * @param mixed  $content    String or Component
     * @param string $height     CSS max-height (ignored for horizontal)
     * @param string $direction  vertical|horizontal|both
     */
    public static function scrollArea(mixed $content, string $height = '320px', string $direction = 'vertical'): ScrollArea
    {
        return new ScrollArea($content, $height, $direction);
    }

    // ── Overlays ──────────────────────────────────────────────────────────────

    /**
     * Modal dialog — uses native <dialog> + showModal() API.
     * Use Modal::trigger($id, $label) to render the open button separately.
     *
     * @param 'sm'|'md'|'lg'|'xl' $size
     */
    public static function modal(string $id, string $title, mixed $content, mixed $footer = null, string $size = 'md'): Modal
    {
        return new Modal($id, $title, $content, $footer, $size);
    }

    /**
     * Toast notification (auto-dismissing).
     *
     * @param 'success'|'warning'|'danger'|'info' $type
     * @param int $dismissAfter Seconds; 0 = manual only
     */
    public static function toast(string $message, string $type = 'info', ?string $title = null, int $dismissAfter = 4): Toast
    {
        return new Toast($message, $type, $title, $dismissAfter);
    }

    /**
     * Slide-in drawer panel.
     * Use Drawer::trigger($id, $label) to render the open button separately.
     *
     * @param 'right'|'left' $position
     */
    public static function drawer(string $id, string $title, mixed $content, mixed $footer = null, string $position = 'right', string $width = '380px'): Drawer
    {
        return new Drawer($id, $title, $content, $footer, $position, $width);
    }

    /**
     * CSS-only tooltip wrapper.
     *
     * @param string $placement top|bottom|left|right
     */
    public static function tooltip(mixed $content, string $text, string $placement = 'top'): Tooltip
    {
        return new Tooltip($content, $text, $placement);
    }

    // ── Data visualization ────────────────────────────────────────────────────

    /**
     * SVG / CSS chart.
     *
     * @param array<string,int|float> $data    label => value
     * @param 'bar'|'line'|'pie'|'donut' $type
     * @param string[] $colors  CSS color overrides
     */
    public static function chart(array $data, string $type = 'bar', int $height = 200, ?string $label = null, array $colors = []): Chart
    {
        return new Chart($data, $type, $height, $label, $colors);
    }

    /**
     * Client-side sortable / searchable / paginated table.
     *
     * @param string[]                    $headers
     * @param array                       $rows
     * @param array<string|callable>|null $cols
     * @param int[]                       $nosort  Column indices to disable sorting on
     */
    public static function dataTable(array $headers, array $rows, ?array $cols = null, string $empty = 'No records found.', int $perPage = 15, bool $searchable = true, array $nosort = []): DataTable
    {
        return new DataTable($headers, $rows, $cols, $empty, $perPage, $searchable, $nosort);
    }

    // ── Layout ────────────────────────────────────────────────────────────────

    /**
     * Two-column layout with a sidebar.
     *
     * @param 'left'|'right' $position
     */
    public static function sidebarLayout(mixed $sidebar, mixed $content, string $width = '240px', string $position = 'left', string $gap = '20px'): SidebarLayout
    {
        return new SidebarLayout($sidebar, $content, $width, $position, $gap);
    }

    /**
     * ⌘K / Ctrl+K command palette overlay.
     * Place once at the bottom of the page.
     *
     * @param array<array{label:string, url:string, category?:string, shortcut?:string, icon?:string}> $items
     */
    public static function commandPalette(array $items, string $placeholder = 'Search commands…'): CommandPalette
    {
        return new CommandPalette($items, $placeholder);
    }

    // ── Tags / Banners / Copy ─────────────────────────────────────────────────

    /**
     * Inline chip/tag — optionally linked or removable.
     *
     * @param 'default'|'success'|'warning'|'danger'|'info'|'accent' $variant
     */
    public static function tag(string $label, string $variant = 'default', ?string $href = null, bool $removable = false, ?string $removeUrl = null): Tag
    {
        return new Tag($label, $variant, $href, $removable, $removeUrl);
    }

    /**
     * Wrapper for a group of tags.
     *
     * @param Tag[] $tags
     */
    public static function tags(array $tags): RawHtml
    {
        return new RawHtml('<div class="sofy-tags">' . implode('', array_map(fn($t) => (string) $t, $tags)) . '</div>');
    }

    /**
     * Full-width announcement banner.
     *
     * @param 'info'|'success'|'warning'|'danger' $variant
     */
    public static function banner(string $message, string $variant = 'info', mixed $action = null, bool $dismissible = false): Banner
    {
        return new Banner($message, $variant, $action, $dismissible);
    }

    /**
     * Button that copies text to clipboard.
     *
     * @param 'sm'|'md'|'lg' $size
     */
    public static function copyButton(string $text, string $label = 'Copy', string $copiedLabel = 'Copied!', string $size = 'sm'): CopyButton
    {
        return new CopyButton($text, $label, $copiedLabel, $size);
    }

    // ── Dev tools ─────────────────────────────────────────────────────────────

    /**
     * Development debug bar (fixed bottom toolbar).
     * Only render when APP_DEBUG=true.
     */
    public static function debugBar(): DebugBar
    {
        return new DebugBar();
    }
}
