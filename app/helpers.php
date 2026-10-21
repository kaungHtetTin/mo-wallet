<?php

function t(string $key): string
{
    static $translations;
    $translations ??= require BASE_PATH . '/app/lang/my.php';
    return ($_SESSION['locale'] ?? 'en') === 'my' ? ($translations[$key] ?? $key) : $key;
}

function money(int|string|null $amount): string
{
    return number_format((int) $amount) . ' ' . t('MMK');
}

function input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? (in_array($key,['password','password_confirmation','current_password'],true) ? $value : trim($value)) : '';
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : '';
}

function field_value(string $key, array $row = [], string $default = ''): string
{
    return (string) old($key, $row[$key] ?? $default);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function form_error(string $name): string
{
    return isset($_SESSION['errors'][$name]) ? '<span class="field-error">' . e(t($_SESSION['errors'][$name])) . '</span>' : '';
}

function page_link(string $path, array $changes = []): string
{
    $params = array_filter(array_merge($_GET, $changes), fn ($v) => is_scalar($v) && $v !== '' && $v !== null);
    return url($path) . ($params ? '?' . http_build_query($params) : '');
}

function icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'transactions' => '<path d="M4 7h16m-4-4 4 4-4 4M20 17H4m4-4-4 4 4 4"/>',
        'books' => '<path d="M4 4h13a2 2 0 0 1 2 2v15H6a2 2 0 0 1-2-2zm0 13h15M8 7h7M8 10h4"/>',
        'businesses' => '<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4M3 12h18M10 12v3h4v-3"/>',
        'categories' => '<path d="M20 13 13 20a2 2 0 0 1-3 0L3 13V3h10l7 7a2 2 0 0 1 0 3Z"/><circle cx="7.5" cy="7.5" r="1"/>',
        'people' => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 8m2 4a6 6 0 0 1 3 5"/>',
        'debts' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h3"/>',
        'reports' => '<path d="M3 3v18h18M7 16v-4m5 4V7m5 9V4"/>',
        'settings' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="16" cy="17" r="3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow-up' => '<path d="m6 12 6-6 6 6M12 6v14"/>',
        'arrow-down' => '<path d="m6 12 6 6 6-6M12 4v14"/>',
        'wallet' => '<path d="M20 8V5H5a2 2 0 0 0 0 4h16v12H5a2 2 0 0 1-2-2V7m13 6h5v4h-5z"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/>',
        'logout' => '<path d="M9 4H4v16h5m5-13 5 5-5 5M9 12h10"/>',
        'download' => '<path d="M12 3v12m-4-4 4 4 4-4M4 16v5h16v-5"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
    ];
    return '<svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['books']) . '</svg>';
}

function debt_status(array $row): string
{
    if ((int) $row['remaining'] === 0) return 'Settled';
    if ($row['due_date'] && $row['due_date'] < date('Y-m-d')) return 'Overdue';
    return (int) $row['paid'] > 0 ? 'Partial' : 'Unpaid';
}

function status_badge(string $status): string
{
    $class = match ($status) { 'Settled', 'income', 'receivable' => 'success', 'Overdue', 'expense', 'payable' => 'danger', 'Partial' => 'warning', default => 'neutral' };
    return '<span class="badge ' . $class . '"><span aria-hidden="true">●</span> ' . e(t(ucfirst($status))) . '</span>';
}

function text_field(string $name, string $label, array $row = [], string $type = 'text', bool $required = true, string $default = '', string $extra = ''): void
{
    echo '<label class="field"><span>' . e(t($label)) . ($required ? ' <span class="required">*</span>' : '') . '</span><input name="' . e($name) . '" type="' . e($type) . '" value="' . e(field_value($name,$row,$default)) . '"' . ($required ? ' required' : '') . ' ' . $extra . '>' . form_error($name) . '</label>';
}

function select_field(string $name, string $label, array $options, array $row = [], bool $required = true, string $default = ''): void
{
    echo '<label class="field"><span>' . e(t($label)) . ($required ? ' <span class="required">*</span>' : '') . '</span><select name="' . e($name) . '"' . ($required ? ' required' : '') . '><option value="">' . e(t($required ? 'Select an option' : 'None')) . '</option>';
    foreach ($options as $key => $option) {
        $value = is_array($option) ? $option['id'] : $key;
        $label = is_array($option) ? $option['name'] : t($option);
        echo '<option value="' . e($value) . '"' . selected(field_value($name,$row,$default),$value) . (is_array($option) && isset($option['kind']) ? ' data-kind="' . e($option['kind']) . '"' : '') . '>' . e($label) . '</option>';
    }
    echo '</select>' . form_error($name) . '</label>';
}

function notes_field(array $row): void
{
    echo '<label class="field span-2"><span>' . e(t('Notes')) . '</span><textarea name="notes" rows="3" maxlength="1000">' . e(field_value('notes',$row)) . '</textarea>' . form_error('notes') . '</label>';
}

function current_route(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
    $base = rtrim(parse_url(base_url(),PHP_URL_PATH) ?: '', '/');
    if ($base && str_starts_with($path,$base . '/')) $path = substr($path,strlen($base));
    elseif ($path === $base) $path = '';
    return trim($path,'/') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
}

function mutation_field(): string
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['mutations'][$token] = time();
    if (count($_SESSION['mutations']) > 100) $_SESSION['mutations'] = array_slice($_SESSION['mutations'], -100, null, true);
    return '<input type="hidden" name="_request" value="' . $token . '">';
}
