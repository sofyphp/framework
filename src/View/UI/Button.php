<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Button extends Component
{
    /**
     * @param 'primary'|'ghost'|'warning'|'danger'|'success' $variant
     * @param 'sm'|'md'|'lg'                        $size
     */
    public function __construct(
        private readonly string  $label,
        private readonly string  $href    = '#',
        private readonly string  $variant = 'ghost',
        private readonly string  $size    = 'md',
        private readonly ?string $method  = null,   // DELETE / PUT / PATCH → hidden form
        private readonly ?string $confirm = null,   // JS confirm message
    ) {}

    public function render(): string
    {
        $cls  = 'sofy-btn sofy-btn-' . $this->e($this->variant);
        $cls .= $this->size !== 'md' ? ' sofy-btn-' . $this->e($this->size) : '';

        // Non-GET/POST → wrap in a mini form with _method override
        if ($this->method !== null) {
            $token   = function_exists('csrf_token') ? csrf_token() : '';
            $confirm = $this->confirm
                ? ' onclick="return confirm(\'' . addslashes($this->confirm) . '\')"'
                : '';
            return sprintf(
                '<form action="%s" method="POST" style="display:inline">'
                . '<input type="hidden" name="_method" value="%s">'
                . '<input type="hidden" name="_token" value="%s">'
                . '<button type="submit" class="%s"%s>%s</button>'
                . '</form>',
                $this->e($this->href),
                $this->e(strtoupper($this->method)),
                $this->e($token),
                $cls,
                $confirm,
                $this->e($this->label)
            );
        }

        $confirm = $this->confirm
            ? ' onclick="return confirm(\'' . addslashes($this->confirm) . '\')"'
            : '';

        return sprintf(
            '<a href="%s" class="%s"%s%s>%s</a>',
            $this->e($this->href),
            $cls,
            $confirm,
            $this->hxString(),
            $this->e($this->label)
        );
    }
}
