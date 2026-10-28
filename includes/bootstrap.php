<?php
declare(strict_types=1);

$CONFIG = require __DIR__ . '/config.php';

function cfg(string $key)
{
    global $CONFIG;
    return $CONFIG[$key] ?? null;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

date_default_timezone_set('Africa/Blantyre');

require __DIR__ . '/supabase.php';
require __DIR__ . '/paychangu.php';
require __DIR__ . '/layout.php';

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)cfg('site_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

function setting(string $key, string $default = 'false'): string
{
    $rows = sb_select('settings', ['key' => 'eq.' . $key, 'select' => 'value']);
    return $rows ? (string)$rows[0]['value'] : $default;
}

function set_setting(string $key, string $value): void
{
    sb_upsert('settings', ['key' => $key, 'value' => $value], 'key');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        // Works even on hosts without the mbstring extension
        $out .= function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($p, 0, 1))
            : strtoupper(substr($p, 0, 1));
    }
    return $out ?: '?';
}

function fmt_time(?string $iso): string
{
    if (!$iso) return '';
    return (new DateTime($iso))->setTimezone(new DateTimeZone('Africa/Blantyre'))->format('d M, H:i');
}

// Categories with their nominees and vote totals (paid ballots only)
function results_by_category(): array
{
    $cats = sb_select('categories', ['select' => 'id,name', 'order' => 'sort_order.asc,id.asc']);
    $rows = sb_select('results', ['select' => '*', 'order' => 'total_votes.desc,name.asc']);
    $by = [];
    foreach ($rows as $r) {
        $by[$r['category_id']][] = $r;
    }
    foreach ($cats as &$c) {
        $c['nominees'] = $by[$c['id']] ?? [];
        $c['total'] = array_sum(array_map(fn($n) => (int)$n['total_votes'], $c['nominees']));
    }
    unset($c);
    return $cats;
}
