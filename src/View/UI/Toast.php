<?php

declare(strict_types=1);

namespace Sofy\View\UI;

/**
 * Toast notifications — rendered server-side, JS auto-dismisses them.
 *
 * Single toast:
 *   UI::toast('File saved!', 'success')
 *   UI::toast('Oops.', 'danger', 'Error', 6)
 *
 * Multiple from flash messages:
 *   Toast::many([
 *       ['message' => 'Saved!',   'type' => 'success'],
 *       ['message' => 'Warning',  'type' => 'warning', 'title' => 'Heads up'],
 *   ])
 */
class Toast extends Component
{
    /** @var array<array{message:string, type:string, title:string|null, dismissAfter:int}> */
    private array $items;

    /**
     * @param string  $type         success|warning|danger|info
     * @param int     $dismissAfter Seconds before auto-dismiss; 0 = permanent
     */
    public function __construct(
        string  $message,
        string  $type         = 'info',
        ?string $title        = null,
        int     $dismissAfter = 4,
    ) {
        $this->items = [compact('message', 'type', 'title', 'dismissAfter')];
    }

    /**
     * Create a multi-toast stack.
     *
     * @param array<array{message:string, type?:string, title?:string, dismissAfter?:int}> $items
     */
    public static function many(array $items): static
    {
        $instance        = new static('', 'info');
        $instance->items = array_map(static fn($it) => [
            'message'      => (string) ($it['message'] ?? ''),
            'type'         => (string) ($it['type']    ?? 'info'),
            'title'        => isset($it['title']) ? (string) $it['title'] : null,
            'dismissAfter' => (int) ($it['dismissAfter'] ?? 4),
        ], $items);
        return $instance;
    }

    public function render(): string
    {
        $active = array_filter($this->items, fn($i) => $i['message'] !== '');
        if (empty($active)) {
            return '';
        }

        $icons = ['success' => '✓', 'warning' => '⚠', 'danger' => '✕', 'info' => 'ℹ'];

        $html = '<div class="sofy-toast-tray" id="sofy-toast-tray">';
        foreach ($active as $item) {
            $type  = $this->e($item['type']);
            $icon  = $icons[$item['type']] ?? 'ℹ';
            $after = (int) $item['dismissAfter'];

            $html .= '<div class="sofy-toast sofy-toast-' . $type . '" data-dismiss="' . $after . '">';
            $html .= '<span class="sofy-toast-icon">' . $icon . '</span>';
            $html .= '<div class="sofy-toast-body">';
            if ($item['title'] !== null) {
                $html .= '<div class="sofy-toast-title">' . $this->e($item['title']) . '</div>';
            }
            $html .= '<div class="sofy-toast-msg">' . $this->e($item['message']) . '</div>';
            $html .= '</div>';
            $html .= '<button class="sofy-toast-close" onclick="this.closest(\'.sofy-toast\').remove()" aria-label="Close">✕</button>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
