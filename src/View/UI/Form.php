<?php

declare(strict_types=1);

namespace Sofy\View\UI;

class Form extends Component
{
    private array   $fields       = [];
    private ?string $submitLabel  = null;
    private string  $submitVariant = 'primary';
    private array   $errors       = [];

    public function __construct(
        private readonly string $action,
        private readonly string $method = 'POST',
    ) {}

    // ── Field builders ────────────────────────────────────────────────────────

    public function input(
        string  $label,
        string  $name,
        string  $type        = 'text',
        mixed   $value       = null,
        string  $placeholder = '',
        bool    $required    = false,
        ?string $hint        = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'type', 'value', 'placeholder', 'required', 'hint')
            + ['kind' => 'input'];
        return $this;
    }

    public function email(string $label, string $name, mixed $value = null, bool $required = false, ?string $hint = null): static
    {
        return $this->input($label, $name, 'email', $value, '', $required, $hint);
    }

    public function password(string $label, string $name, bool $required = false, ?string $hint = null): static
    {
        return $this->input($label, $name, 'password', null, '', $required, $hint);
    }

    public function number(string $label, string $name, mixed $value = null, bool $required = false, ?string $hint = null): static
    {
        return $this->input($label, $name, 'number', $value, '', $required, $hint);
    }

    public function date(string $label, string $name, mixed $value = null, bool $required = false, ?string $hint = null): static
    {
        return $this->input($label, $name, 'date', $value, '', $required, $hint);
    }

    public function range(string $label, string $name, int $min = 0, int $max = 100, mixed $value = null, ?string $hint = null): static
    {
        $this->fields[] = compact('label', 'name', 'min', 'max', 'value', 'hint') + ['kind' => 'range'];
        return $this;
    }

    public function hidden(string $name, mixed $value): static
    {
        $this->fields[] = ['kind' => 'hidden', 'name' => $name, 'value' => $value];
        return $this;
    }

    public function select(
        string  $label,
        string  $name,
        array   $options,
        mixed   $selected = null,
        bool    $required = false,
        ?string $hint     = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'options', 'selected', 'required', 'hint')
            + ['kind' => 'select'];
        return $this;
    }

    public function textarea(
        string  $label,
        string  $name,
        mixed   $value       = null,
        int     $rows        = 4,
        string  $placeholder = '',
        bool    $required    = false,
        ?string $hint        = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'value', 'rows', 'placeholder', 'required', 'hint')
            + ['kind' => 'textarea'];
        return $this;
    }

    /**
     * Searchable select — filters options as you type (see UI::combobox).
     * Pass an endpoint to fetch options remotely from a Search-backed route.
     *
     * @param array<int|string,mixed> $options [value => label] or list of
     *                                          ['value' => …, 'label' => …]
     */
    public function combobox(
        string  $label,
        string  $name,
        array   $options,
        mixed   $selected    = null,
        bool    $required    = false,
        string  $placeholder = 'Search…',
        ?string $endpoint    = null,
        ?string $hint        = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'options', 'selected', 'required', 'placeholder', 'endpoint', 'hint')
            + ['kind' => 'combobox'];
        return $this;
    }

    public function checkbox(string $label, string $name, bool $checked = false, ?string $hint = null): static
    {
        $this->fields[] = compact('label', 'name', 'checked', 'hint') + ['kind' => 'checkbox'];
        return $this;
    }

    public function radio(
        string  $label,
        string  $name,
        array   $options,
        mixed   $selected = null,
        ?string $hint     = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'options', 'selected', 'hint')
            + ['kind' => 'radio'];
        return $this;
    }

    public function toggle(string $label, string $name, bool $checked = false, ?string $hint = null): static
    {
        $this->fields[] = compact('label', 'name', 'checked', 'hint') + ['kind' => 'toggle'];
        return $this;
    }

    public function file(
        string  $label,
        string  $name,
        ?string $accept   = null,
        bool    $required = false,
        ?string $hint     = null,
    ): static {
        $this->fields[] = compact('label', 'name', 'accept', 'required', 'hint')
            + ['kind' => 'file'];
        return $this;
    }

    /** Start a 2-column row — wrap the next two field calls in a grid. */
    public function cols(callable $fn): static
    {
        $this->fields[] = ['kind' => '_cols_start'];
        $fn($this);
        $this->fields[] = ['kind' => '_cols_end'];
        return $this;
    }

    public function submit(string $label = 'Submit', string $variant = 'primary'): static
    {
        $this->submitLabel   = $label;
        $this->submitVariant = $variant;
        return $this;
    }

    /** Pass validation errors: ['field' => 'message', ...] */
    public function withErrors(array $errors): static
    {
        $this->errors = $errors;
        return $this;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): string
    {
        $isOverride = !in_array(strtoupper($this->method), ['GET', 'POST'], true);
        $formMethod = $isOverride ? 'POST' : strtoupper($this->method);
        $token      = function_exists('csrf_token') ? csrf_token() : '';

        $html = '<form action="' . $this->e($this->action) . '" method="' . $formMethod . '">';

        if ($isOverride) {
            $html .= '<input type="hidden" name="_method" value="' . $this->e(strtoupper($this->method)) . '">';
        }
        if ($token !== '') {
            $html .= '<input type="hidden" name="_token" value="' . $this->e($token) . '">';
        }

        foreach ($this->fields as $f) {
            if ($f['kind'] === '_cols_start') {
                $html .= '<div class="sofy-form-cols">';
                continue;
            }
            if ($f['kind'] === '_cols_end') {
                $html .= '</div>';
                continue;
            }
            $html .= $this->renderField($f);
        }

        if ($this->submitLabel !== null) {
            $html .= '<div class="sofy-form-footer">'
                . '<button type="submit" class="sofy-btn sofy-btn-' . $this->e($this->submitVariant) . '">'
                . $this->e($this->submitLabel)
                . '</button>'
                . '</div>';
        }

        return $html . '</form>';
    }

    private function renderField(array $f): string
    {
        $name  = $f['name'];
        $error = $this->errors[$name] ?? null;

        if ($f['kind'] === 'hidden') {
            return '<input type="hidden" name="' . $this->e($name) . '" value="' . $this->e((string) $f['value']) . '">';
        }

        $errorHtml = $error ? '<div class="sofy-form-err">' . $this->e($error) . '</div>' : '';
        $hintHtml  = isset($f['hint']) && $f['hint'] ? '<div class="sofy-form-hint">' . $this->e($f['hint']) . '</div>' : '';
        $req       = ($f['required'] ?? false) ? '<span class="sofy-form-req">*</span>' : '';
        $label     = '<label class="sofy-form-label" for="' . $this->e($name) . '">'
            . $this->e($f['label']) . $req . '</label>';

        $ctrl = match ($f['kind']) {
            'input'    => $this->renderInput($f),
            'select'   => $this->renderSelect($f),
            'combobox' => $this->renderCombobox($f),
            'textarea' => $this->renderTextarea($f),
            'checkbox' => $this->renderCheckbox($f),
            'radio'    => $this->renderRadio($f),
            'toggle'   => $this->renderToggle($f),
            'file'     => $this->renderFile($f),
            'range'    => $this->renderRange($f),
            default    => '',
        };

        if ($f['kind'] === 'checkbox' || $f['kind'] === 'toggle') {
            return '<div class="sofy-form-row">' . $ctrl . $hintHtml . $errorHtml . '</div>';
        }

        // Text-like fields carry the transitions.dev error-shake hooks: the row
        // is the .t-input-wrap, the control is the .t-input, and data-error lets
        // the JS replay the shake + flag the error border on load.
        if (in_array($f['kind'], ['input', 'select', 'textarea', 'combobox'], true)) {
            $wrapAttr = $error ? ' data-error="1"' : '';
            return '<div class="sofy-form-row t-input-wrap"' . $wrapAttr . '>'
                . $label . $ctrl . $hintHtml . $errorHtml . '</div>';
        }

        return '<div class="sofy-form-row">' . $label . $ctrl . $hintHtml . $errorHtml . '</div>';
    }

    private function renderInput(array $f): string
    {
        $val = $f['value'] ?? (function_exists('old') ? old($f['name']) : null);
        return '<input'
            . ' type="' . $this->e($f['type']) . '"'
            . ' id="' . $this->e($f['name']) . '"'
            . ' name="' . $this->e($f['name']) . '"'
            . ' class="sofy-form-ctrl t-input"'
            . ($val !== null ? ' value="' . $this->e((string) $val) . '"' : '')
            . ($f['placeholder'] !== '' ? ' placeholder="' . $this->e($f['placeholder']) . '"' : '')
            . ($f['required'] ? ' required' : '')
            . '>';
    }

    private function renderSelect(array $f): string
    {
        $cur  = $f['selected'] ?? (function_exists('old') ? old($f['name']) : null);
        $opts = '';
        foreach ($f['options'] as $val => $text) {
            $sel   = (string) $val === (string) $cur ? ' selected' : '';
            $opts .= '<option value="' . $this->e((string) $val) . '"' . $sel . '>'
                . $this->e((string) $text) . '</option>';
        }
        return '<select id="' . $this->e($f['name']) . '" name="' . $this->e($f['name']) . '"'
            . ' class="sofy-form-ctrl t-input"'
            . ($f['required'] ? ' required' : '')
            . '>' . $opts . '</select>';
    }

    private function renderCombobox(array $f): string
    {
        $cur = $f['selected'] ?? (function_exists('old') ? old($f['name']) : null);
        $box = new Combobox($f['name'], $f['options'], $cur !== null ? (string) $cur : null);
        $box->placeholder((string) ($f['placeholder'] ?? 'Search…'));
        if ($f['required'] ?? false) {
            $box->required();
        }
        if (!empty($f['endpoint'])) {
            $box->endpoint((string) $f['endpoint']);
        }
        return $box->render();
    }

    private function renderTextarea(array $f): string
    {
        $val = $f['value'] ?? (function_exists('old') ? old($f['name']) : null);
        return '<textarea'
            . ' id="' . $this->e($f['name']) . '"'
            . ' name="' . $this->e($f['name']) . '"'
            . ' class="sofy-form-ctrl t-input"'
            . ' rows="' . (int) $f['rows'] . '"'
            . ($f['placeholder'] !== '' ? ' placeholder="' . $this->e($f['placeholder']) . '"' : '')
            . ($f['required'] ? ' required' : '')
            . '>' . $this->e((string) ($val ?? '')) . '</textarea>';
    }

    private function renderCheckbox(array $f): string
    {
        $checked = $f['checked'] ? ' checked' : '';
        return '<label class="sofy-form-check">'
            . '<input type="checkbox" class="sofy-form-check-cb" name="' . $this->e($f['name']) . '" value="1"' . $checked . '>'
            . '<span class="sofy-form-check-lbl">' . $this->e($f['label']) . '</span>'
            . '</label>';
    }

    private function renderRadio(array $f): string
    {
        $cur  = $f['selected'] ?? (function_exists('old') ? old($f['name']) : null);
        $opts = '';
        foreach ($f['options'] as $val => $text) {
            $checked = (string) $val === (string) $cur ? ' checked' : '';
            $opts .= '<label class="sofy-radio">'
                . '<input type="radio" class="sofy-radio-cb" name="' . $this->e($f['name']) . '" value="' . $this->e((string) $val) . '"' . $checked . '>'
                . '<span class="sofy-radio-lbl">' . $this->e((string) $text) . '</span>'
                . '</label>';
        }
        return '<div class="sofy-radio-group">' . $opts . '</div>';
    }

    private function renderToggle(array $f): string
    {
        $checked = $f['checked'] ? ' checked' : '';
        return '<label class="sofy-toggle">'
            . '<input type="checkbox" class="sofy-toggle-cb" name="' . $this->e($f['name']) . '" value="1"' . $checked . '>'
            . '<span class="sofy-toggle-track"><span class="sofy-toggle-thumb"></span></span>'
            . '<span class="sofy-toggle-lbl">' . $this->e($f['label']) . '</span>'
            . '</label>';
    }

    private function renderFile(array $f): string
    {
        $accept   = $f['accept'] ? ' accept="' . $this->e($f['accept']) . '"' : '';
        $required = $f['required'] ? ' required' : '';
        return '<input type="file"'
            . ' id="' . $this->e($f['name']) . '"'
            . ' name="' . $this->e($f['name']) . '"'
            . ' class="sofy-form-file"'
            . $accept
            . $required
            . '>';
    }

    private function renderRange(array $f): string
    {
        $val = $f['value'] ?? (function_exists('old') ? old($f['name']) : $f['min']);
        $val = $val ?? $f['min'];
        return '<div class="sofy-range-wrap">'
            . '<input type="range"'
            . ' id="' . $this->e($f['name']) . '"'
            . ' name="' . $this->e($f['name']) . '"'
            . ' class="sofy-form-range"'
            . ' min="' . (int) $f['min'] . '"'
            . ' max="' . (int) $f['max'] . '"'
            . ' value="' . $this->e((string) $val) . '"'
            . ' oninput="this.nextElementSibling.textContent=this.value">'
            . '<output class="sofy-range-val">' . $this->e((string) $val) . '</output>'
            . '</div>';
    }
}
