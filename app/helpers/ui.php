<?php

/** Shared feature checks for buttons and their modal contents. */
function uiCan(string $module, string $section, string $action = 'lihat'): bool
{
    return (new RoleMiddleware())->check($module, $section, $action);
}

/**
 * Server-rendered UI primitives.
 *
 * Komponen di sini sengaja tipis: markup tetap semantik, sedangkan detail
 * visual berada di public/assets/css/components.css. Semua nilai dinamis
 * di-escape sebelum dirender; $content pada uiCard() memang HTML terkontrol
 * dari developer dan tidak di-escape.
 */

/**
 * Optional control spacing, expressed in design px and scaled with --ui-unit.
 * Values are numbers, not arbitrary CSS strings.
 */
function uiControlSpacing(array $options): string
{
    $style = '';
    foreach (['paddingHorizontal' => 'padding-inline', 'paddingVertical' => 'padding-block', 'marginVertical' => 'margin-block'] as $option => $property) {
        if (!array_key_exists($option, $options)) {
            continue;
        }
        $value = $options[$option];
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) {
            throw new InvalidArgumentException($option . ' harus berupa angka positif atau nol.');
        }
        $style .= '--ui-control-' . $property . ': calc(' . ((float) $value / 16) . ' * var(--ui-unit));';
    }
    return $style;
}

/**
 * Native button. Variants: primary, outline, disabled, tabular-active, tabular-inactive.
 * Options: type (button/submit/reset), icon (asset name), iconPosition (left/right),
 * iconOnly, fullWidth, disabled, paddingHorizontal (12), paddingVertical (8),
 * marginVertical (8), class, attributes. Label becomes aria-label for iconOnly.
 */
function uiButton(string $label, string $variant = 'primary', array $options = []): string
{
    $variants = ['primary', 'outline', 'outline-danger', 'disabled', 'tabular-active', 'tabular-inactive'];
    if (!in_array($variant, $variants, true)) {
        throw new InvalidArgumentException('Variant button tidak tersedia: ' . $variant);
    }
    $iconName = (string) ($options['icon'] ?? '');
    $iconOnly = !empty($options['iconOnly']);
    if (trim($label) === '' || ($iconOnly && $iconName === '')) {
        throw new InvalidArgumentException('Button membutuhkan label; iconOnly juga membutuhkan icon.');
    }
    if ($iconName !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $iconName)) {
        throw new InvalidArgumentException('Nama icon tidak valid.');
    }
    $iconMarkup = $iconName === '' ? '' : icon($iconName);
    if ($iconName !== '' && $iconMarkup === '') {
        throw new InvalidArgumentException('Asset icon tidak ditemukan: ' . $iconName);
    }
    $attributes = $options['attributes'] ?? [];
    $disabled = $variant === 'disabled' || !empty($options['disabled']) || !empty($attributes['disabled']);
    $type = $options['type'] ?? 'button';
    $attributes['type'] = in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
    $attributes['disabled'] = $disabled;
    $attributes['class'] = uiClassList([
        'ui-button', 'ui-button--' . ($disabled ? 'disabled' : $variant),
        $iconOnly ? 'ui-button--icon-only' : '',
        !empty($options['fullWidth']) ? 'ui-control--full' : '',
        (string) ($options['class'] ?? ''),
    ]);
    $attributes['style'] = trim((string) ($attributes['style'] ?? ''), '; ') . ';' . uiControlSpacing($options);
    if (trim($attributes['style'], ';') === '') {
        unset($attributes['style']);
    }
    if ($iconOnly) {
        $attributes['aria-label'] = $label;
    }
    if (str_starts_with($variant, 'tabular-') && !isset($attributes['aria-pressed'])) {
        $attributes['aria-pressed'] = $variant === 'tabular-active' ? 'true' : 'false';
    }
    $iconMarkup = $iconMarkup === '' ? '' : '<span class="ui-button-icon" aria-hidden="true">' . $iconMarkup . '</span>';
    $text = $iconOnly ? '' : '<span class="ui-button-label">' . e($label) . '</span>';
    $content = ($options['iconPosition'] ?? 'left') === 'right' ? $text . $iconMarkup : $iconMarkup . $text;
    return '<button ' . uiAttrs($attributes) . '>' . $content . '</button>';
}

/**
 * Custom dropdown filter with native fallback; choices are [value => label].
 * Options: value, id, disabled, fullWidth, class, attributes and control spacing.
 * Selection is submitted by the surrounding form, never auto-submitted here.
 */
function uiFilter(string $name, string $label, array $choices, array $options = []): string
{
    if (trim($name) === '' || trim($label) === '') {
        throw new InvalidArgumentException('Filter membutuhkan name dan label.');
    }
    $attributes = $options['attributes'] ?? [];
    $disabled = !empty($options['disabled']) || !empty($attributes['disabled']);
    $attributes = array_merge($attributes, [
        'name' => $name, 'id' => $options['id'] ?? $attributes['id'] ?? $name,
        'aria-label' => $label, 'disabled' => $disabled, 'class' => 'ui-filter-select',
    ]);
    $wrapper = [
        'class' => uiClassList(['ui-filter', $disabled ? 'is-disabled' : '',
            !empty($options['fullWidth']) ? 'ui-control--full' : '', (string) ($options['class'] ?? '')]),
        'style' => uiControlSpacing($options) ?: null,
        'data-ui-select' => true,
        'data-ui-filter' => true,
    ];
    $html = '<span ' . uiAttrs($wrapper) . '><label class="ui-visually-hidden" for="' . e((string) $attributes['id']) . '">' . e($label) . '</label><select ' . uiAttrs($attributes) . '>';
    foreach ($choices as $value => $text) {
        $html .= '<option ' . uiAttrs([
            'value' => (string) $value,
            'selected' => array_key_exists('value', $options) && (string) $options['value'] === (string) $value,
        ]) . '>' . e((string) $text) . '</option>';
    }
    return $html . '</select><span class="ui-filter-icon" aria-hidden="true">' . icon('icon_chevron') . '</span><template data-select-chevron>' . icon('icon_chevron') . '</template></span>';
}

function uiAttrs(array $attributes): string
{
    $parts = [];

    foreach ($attributes as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }

        if ($value === true) {
            $parts[] = e((string) $name);
            continue;
        }

        $parts[] = e((string) $name) . '="' . e((string) $value) . '"';
    }

    return implode(' ', $parts);
}

/** Shared modal shell. $content is trusted, server-rendered form markup. */
function uiModal(string $id, string $title, string $content, array $options = []): string
{
    $delete = ($options['variant'] ?? 'form') === 'delete';
    $description = (string) ($options['description'] ?? '');
    $attributes = ['class' => 'modal-box ui-modal' . ($delete ? ' ui-modal--delete' : '') . (($options['variant'] ?? '') === 'assignment' ? ' ui-modal--assignment' : ''),
        'role' => 'dialog', 'aria-modal' => 'true', 'aria-labelledby' => $id . '-title',
        'aria-describedby' => $description !== '' ? $id . '-description' : null];
    return '<div class="modal-overlay" id="' . e($id) . '"><div ' . uiAttrs($attributes) . '>'
        . '<h2 class="modal-title" id="' . e($id) . '-title">' . e($title) . '</h2>'
        . ($description !== '' ? '<p class="ui-modal-description" id="' . e($id) . '-description">' . e($description) . '</p>' : '')
        . $content . '</div></div>';
}

/** Reusable status badge, using only shared color tokens and typography. */
function uiBadge(string $label, string $variant = 'netral'): string
{
    if (!in_array($variant, ['positif', 'peringatan', 'netral', 'destruktif'], true)) {
        throw new InvalidArgumentException('Variant badge tidak tersedia: ' . $variant);
    }
    return '<span class="badge badge-' . $variant . '">' . e($label) . '</span>';
}

function uiClassList(array $classes): string
{
    return implode(' ', array_values(array_filter($classes, static fn($class) => $class !== '')));
}

/**
 * Teks dengan type scale, weight, font, tone, dan alignment yang konsisten.
 *
 * @param array{tag?: string, weight?: string, font?: string, tone?: string,
 *             align?: string, class?: string, attributes?: array} $options
 */
function uiText(string $text, string $variant = 'body-sm', array $options = []): string
{
    $variants = [
        'display-xxl', 'display-xl', 'display-lg', 'display-md', 'display-sm', 'display-xs',
        'headline-lg', 'headline-md', 'headline-sm',
        'body-lg', 'body-md', 'body-sm',
        'caption-lg', 'caption-md', 'caption-sm', 'preview', 'preview-callout',
    ];
    $weights = ['regular', 'bold'];
    $fonts = ['base', 'geist'];
    $tones = ['default', 'heading', 'muted', 'strong', 'brand', 'success', 'status-active', 'status-inactive', 'secondary', 'inverse', 'preview'];
    $alignments = ['start', 'center', 'end'];
    $tags = ['span', 'p', 'div', 'h1', 'h2', 'h3', 'label'];

    $variant = in_array($variant, $variants, true) ? $variant : 'body-sm';
    $weight = in_array($options['weight'] ?? 'regular', $weights, true) ? ($options['weight'] ?? 'regular') : 'regular';
    $font = in_array($options['font'] ?? 'base', $fonts, true) ? ($options['font'] ?? 'base') : 'base';
    $tone = in_array($options['tone'] ?? 'default', $tones, true) ? ($options['tone'] ?? 'default') : 'default';
    $align = in_array($options['align'] ?? '', $alignments, true) ? ($options['align'] ?? '') : '';
    $tag = in_array($options['tag'] ?? 'span', $tags, true) ? ($options['tag'] ?? 'span') : 'span';

    $classes = [
        'ui-text',
        'text-' . $variant,
        'font-' . $weight,
        'font-' . $font,
        $tone !== 'default' ? 'ui-text-tone-' . $tone : '',
        $align !== '' ? 'ui-text-align-' . $align : '',
        (string) ($options['class'] ?? ''),
    ];

    $attributes = $options['attributes'] ?? [];
    $attributes['class'] = uiClassList($classes);

    return '<' . $tag . ' ' . uiAttrs($attributes) . '>' . e($text) . '</' . $tag . '>';
}

/**
 * Field reusable dengan iconPosition left/right (default right), hideLabel,
 * serta iconToggle untuk password. Ikon dekoratif tidak menerima fokus.
 *
 * @param array{type?: string, id?: string, value?: string, placeholder?: string,
 *             state?: string, font?: string, labelTone?: string, variant?: string,
 *             required?: bool, disabled?: bool, readonly?: bool, autocomplete?: string,
 *             icon?: string, iconPosition?: string, hideLabel?: bool,
 *             iconToggle?: bool, error?: string, class?: string,
 *             inputAttributes?: array} $options
 */
function uiField(string $name, string $label, array $options = []): string
{
    $type = $options['type'] ?? 'text';
    $type = in_array($type, ['text', 'email', 'password', 'date', 'search', 'textarea', 'number', 'tel'], true) ? $type : 'text';
    $state = $options['state'] ?? 'default';
    $state = in_array($state, ['default', 'active', 'filled', 'viewonly', 'negative'], true) ? $state : 'default';
    $font = ($options['font'] ?? 'base') === 'geist' ? 'font-geist' : 'font-base';
    $variant = preg_replace('/[^a-z0-9-]/i', '', (string) ($options['variant'] ?? 'default'));
    $id = (string) ($options['id'] ?? $name);
    $error = trim((string) ($options['error'] ?? ''));

    $fieldClasses = [
        'field',
        'ui-field',
        'ui-field--' . ($variant !== '' ? $variant : 'default'),
        'ui-field-state-' . $state,
        (string) ($options['class'] ?? ''),
    ];
    $labelClasses = [
        'field-label',
        !empty($options['hideLabel']) ? 'ui-visually-hidden' : '',
        $font,
        ($options['labelTone'] ?? '') !== '' ? 'ui-field-label-' . preg_replace('/[^a-z0-9-]/i', '', (string) $options['labelTone']) : '',
    ];
    $inputClasses = [$type === 'textarea' ? 'field-textarea' : 'field-input', $font, $state === 'viewonly' ? 'is-viewonly' : '', $state === 'negative' || $error !== '' ? 'is-negative' : ''];

    $inputAttributes = $options['inputAttributes'] ?? [];
    $inputAttributes = array_merge($inputAttributes, [
        'id' => $id,
        'name' => $name,
        'class' => uiClassList($inputClasses),
        'placeholder' => $options['placeholder'] ?? null,
        'autocomplete' => $options['autocomplete'] ?? null,
        'aria-invalid' => $error !== '' ? 'true' : null,
    ]);

    if (!empty($options['required'])) {
        $inputAttributes['required'] = true;
    }
    if (!empty($options['disabled'])) {
        $inputAttributes['disabled'] = true;
    }
    if (!empty($options['readonly']) || $state === 'viewonly') {
        $inputAttributes['readonly'] = true;
    }

    $value = (string) ($options['value'] ?? '');
    if ($type === 'textarea') {
        $control = '<textarea ' . uiAttrs($inputAttributes) . '>' . e($value) . '</textarea>';
    } else {
        $inputAttributes['type'] = $type;
        $inputAttributes['value'] = $value;
        $control = '<input ' . uiAttrs($inputAttributes) . '>';
    }

    $iconName = (string) ($options['icon'] ?? '');
    if ($iconName !== '') {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $iconName) || icon($iconName) === '') {
            throw new InvalidArgumentException('Asset icon tidak valid: ' . $iconName);
        }
        $wrapperClasses = ['field-input-wrapper'];
        $iconToggle = !empty($options['iconToggle']);
        $iconPosition = ($options['iconPosition'] ?? 'right') === 'left' ? 'left' : 'right';
        $wrapperClasses[] = 'has-' . $iconPosition . '-icon';

        if ($iconToggle) {
            $control = '<div class="' . uiClassList($wrapperClasses) . '">' . $control
                . '<button type="button" class="field-icon-toggle" data-password-toggle aria-controls="' . e($id) . '" aria-pressed="false" aria-label="Tampilkan kata sandi">'
                . '<span data-password-show aria-hidden="true">' . icon($iconName) . '</span>'
                . '<span data-password-hide aria-hidden="true" hidden>' . icon('icon_eye_off') . '</span>'
                . '</button></div>';
        } elseif (!empty($options['iconCalendar']) && $type === 'date') {
            $control = '<div class="' . uiClassList($wrapperClasses) . '" data-datepicker>' . $control
                . '<button type="button" class="field-icon-toggle" data-datepicker-toggle aria-label="' . e('Pilih ' . $label) . '" aria-expanded="false" aria-haspopup="dialog">'
                . '<span aria-hidden="true">' . icon($iconName) . '</span></button></div>';
        } else {
            $control = '<div class="' . uiClassList($wrapperClasses) . '">' . $control
                . '<span class="field-icon field-icon-' . $iconPosition . '" aria-hidden="true">' . icon($iconName) . '</span></div>';
        }
    }

    $html = '<div class="' . uiClassList($fieldClasses) . '">'
        . '<label for="' . e($id) . '" class="' . uiClassList($labelClasses) . '">' . e($label) . '</label>'
        . $control;

    if ($error !== '') {
        $html .= '<span class="field-error" role="alert">' . e($error) . '</span>';
    }

    return $html . '</div>';
}

/**
 * Checkbox dengan label dan variant teks yang dapat dipakai ulang.
 * Indeterminate state ditandai data attribute dan diaktifkan oleh JavaScript.
 */
function uiCheckbox(string $name, string $label, bool $checked = false, array $options = []): string
{
    $id = (string) ($options['id'] ?? $name);
    $textVariant = (string) ($options['textVariant'] ?? 'caption-md');
    $textOptions = [
        'weight' => $options['weight'] ?? 'regular',
        'font' => $options['font'] ?? 'base',
        'tone' => $options['tone'] ?? 'muted',
    ];

    $inputAttributes = $options['inputAttributes'] ?? [];
    $inputAttributes = array_merge($inputAttributes, [
        'type' => 'checkbox',
        'id' => $id,
        'name' => $name,
        'value' => $options['value'] ?? '1',
        'class' => 'checkbox',
        'checked' => $checked,
        'disabled' => !empty($options['disabled']),
        'data-indeterminate' => !empty($options['indeterminate']) ? 'true' : null,
    ]);

    $variant = ($options['variant'] ?? 'default') === 'login' ? 'login' : 'default';
    return '<label class="checkbox-label ui-checkbox ui-checkbox--' . $variant . '">'
        . '<input ' . uiAttrs($inputAttributes) . '>'
        . uiText($label, $textVariant, $textOptions)
        . '</label>';
}

/**
 * Card wrapper. $content adalah HTML terkontrol dari view/component caller.
 */
function uiCard(string $content, string $variant = 'surface', array $options = []): string
{
    $variants = ['surface', 'brand', 'preview', 'outlined', 'callout'];
    $variant = in_array($variant, $variants, true) ? $variant : 'surface';
    $tag = in_array($options['tag'] ?? 'div', ['div', 'section', 'article'], true) ? ($options['tag'] ?? 'div') : 'div';
    $attributes = $options['attributes'] ?? [];
    $attributes['class'] = uiClassList(['ui-card', 'ui-card--' . $variant, (string) ($options['class'] ?? '')]);

    return '<' . $tag . ' ' . uiAttrs($attributes) . '>' . $content . '</' . $tag . '>';
}

/** Read-only data card. Values remain escaped plain text, never form controls. */
function uiSelect(string $name, string $label, array $choices, array $options = []): string
{
    // Compact form controls: 8px vertical / 12px horizontal; default stays unchanged.
    $compact = ($options['variant'] ?? 'form') === 'compact';
    static $sequence = 0;
    $id = (string) ($options['id'] ?? 'ui-select-' . ++$sequence);
    $error = (string) ($options['error'] ?? '');
    $attrs = [
        'id' => $id, 'name' => $name, 'class' => 'field-input ui-select-native font-geist',
        'disabled' => !empty($options['disabled']), 'required' => !empty($options['required']),
        'aria-invalid' => $error !== '' ? 'true' : null,
        'aria-describedby' => $error !== '' ? $id . '-error' : null,
    ];
    $attrs = array_merge($attrs, $options['attributes'] ?? []);
    $html = '<div class="field ui-field ui-field--form ui-select' . ($compact ? ' ui-field--compact' : '') . '" data-ui-select>'
        . '<label class="field-label font-geist' . (!empty($options['hideLabel']) ? ' ui-visually-hidden' : '') . '" for="' . e($id) . '">' . e($label) . '</label>'
        . '<select ' . uiAttrs($attrs) . '>';
    foreach ($choices as $value => $text) {
        $html .= '<option ' . uiAttrs(['value' => (string) $value, 'selected' => (string) ($options['value'] ?? '') === (string) $value]) . '>' . e((string) $text) . '</option>';
    }
    $html .= '</select><template data-select-chevron>' . icon('icon_chevron') . '</template>';
    if ($error !== '') $html .= '<span class="field-error" id="' . e($id) . '-error" role="alert">' . e($error) . '</span>';
    return $html . '</div>';
}

function uiDataCard(string $label, ?string $value, array $options = []): string
{
    $value = trim((string) $value);
    $hasLink = !empty($options['externalLink'])
        && filter_var($value, FILTER_VALIDATE_URL) !== false
        && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    $link = $hasLink
        ? '<a class="ui-data-card-link" href="' . e($value) . '" target="_blank" rel="noopener noreferrer" aria-label="' . e('Buka ' . $label . ' di tab baru') . '" title="Buka di tab baru"><span aria-hidden="true">' . icon('icon_external_link') . '</span></a>'
        : '';
    return '<dl class="' . e(uiClassList(['ui-data-card', $hasLink ? 'ui-data-card--external' : '', (string) ($options['class'] ?? '')])) . '">'
        . '<dt class="ui-data-card-label">' . e($label) . '</dt>'
        . '<dd class="ui-data-card-value">' . e($value !== '' ? $value : '-') . $link . '</dd></dl>';
}

function uiInlineMeta(string $primary, ?string $secondary = null, array $options = []): string
{
    $classes = uiClassList(['ui-inline-meta', (string) ($options['class'] ?? '')]);
    $html = '<div class="' . $classes . '"><span>' . e($primary) . '</span>';

    if ($secondary !== null && $secondary !== '') {
        $html .= '<span class="ui-inline-meta-dot" aria-hidden="true">•</span><span>' . e($secondary) . '</span>';
    }

    return $html . '</div>';
}
