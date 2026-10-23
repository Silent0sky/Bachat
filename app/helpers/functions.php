<?php

/**
 * Global helper functions available everywhere.
 * (No namespaced classes here on purpose — small, safe, widely used utilities.)
 */

use App\Core\Auth;
use App\Core\CSRF;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Lang;
use App\Core\Session;

/** HTML-escape. Use for EVERY dynamic output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an app URL (relative, works in sub-folders & with the ?r= fallback). */
function url(string $path = '/'): string
{
    $base = (string) config('app.base_url', '');
    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $base   = $scheme . '://' . $host . $dir;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/** Public asset URL. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Uploaded file URL (uploads/... path returned by Upload::file()). */
function upload_url(?string $relPath): string
{
    if (!$relPath) {
        return '';
    }
    return url(ltrim((string) $relPath, '/'));
}

/** Indian-format currency: ₹1,23,456.00 */
function money(mixed $n): string
{
    $n   = (float) $n;
    $neg = $n < 0;
    $n   = abs($n);
    $s   = number_format($n, 2, '.', '');
    [$int, $dec] = array_pad(explode('.', $s, 2), 2, '00');
    if (strlen($int) > 3) {
        $head = substr($int, 0, -3);
        $tail = substr($int, -3);
        $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head) ?? $head;
        $int  = $head . ',' . $tail;
    }
    return ($neg ? '-' : '') . '₹' . $int . '.' . $dec;
}

/** Compact quantity display: 1.500 → 1.5, 2.000 → 2 */
function qty(mixed $n): string
{
    $s = number_format((float) $n, 3, '.', '');
    return rtrim(rtrim($s, '0'), '.');
}

/** Human readable pack size, e.g. 1 kg, 500 g, 2 pc */
function pack_label(mixed $size, ?string $unit): string
{
    $units = ['g' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'l' => 'L', 'pc' => 'pc', 'pack' => 'pack', 'bottle' => 'bottle', 'bag' => 'bag', 'box' => 'box'];
    $u     = $units[$unit] ?? (string) $unit;
    return qty($size) . ' ' . $u;
}

/** Translate. */
function t(string $key, array $replace = []): string
{
    return Lang::get($key, $replace);
}

function lang(): string
{
    return Lang::locale();
}

function csrf_token(): string
{
    return CSRF::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(CSRF::token()) . '">';
}

function flash(string $type, string $message): void
{
    Flash::add($type, $message);
}

function auth_user(): ?array
{
    return Auth::user();
}

function auth_check(): bool
{
    return Auth::check();
}

function auth_role(): ?string
{
    return Auth::role();
}

function auth_society_id(): ?int
{
    return Auth::societyId();
}

/** Dot-notation config access. */
function config(string $key, mixed $default = null): mixed
{
    $cfg  = $GLOBALS['__bachat_config'] ?? [];
    $node = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($node) || !array_key_exists($part, $node)) {
            return $default;
        }
        $node = $node[$part];
    }
    return $node;
}

/** Old form input. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);
    return is_array($old) ? ($old[$key] ?? $default) : $default;
}

/** Current society branding row (null = platform / no society). */
function current_society(): ?array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache['id'] === null ? null : $cache;
    }
    $sid = auth_society_id();
    if ($sid === null) {
        $cache = ['id' => null];
        return null;
    }
    $cache = Database::fetch(
        'SELECT id, name, slug, city, logo, banner, primary_color, default_language, contact_email, contact_phone,
                upi_id, qr_image, delivery_charge, pickup_enabled, delivery_enabled, payment_verifier, status
         FROM societies WHERE id = ? LIMIT 1',
        [$sid]
    );
    return $cache ?: null;
}

/** Product display name in the active language with graceful fallback. */
function product_name(array $row): string
{
    $l = lang();
    return (string) ($row['name_' . $l] ?? '') ?: (string) ($row['name_en'] ?? '');
}

/** Formatted datetime for display. */
function human_dt(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d M Y, h:i A', $ts) : '—';
}

function human_date(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : '—';
}

/** Status → badge class map (used across dashboards). */
function status_badge(string $status): string
{
    $map = [
        'pending' => 'b-badge-amber', 'submitted' => 'b-badge-amber', 'pricing' => 'b-badge-amber',
        'active' => 'b-badge-green', 'open' => 'b-badge-green', 'confirmed' => 'b-badge-green',
        'delivered' => 'b-badge-green', 'completed' => 'b-badge-green', 'verified' => 'b-badge-green',
        'approved' => 'b-badge-green', 'received' => 'b-badge-green', 'paid' => 'b-badge-green',
        'resolved' => 'b-badge-green', 'target_met' => 'b-badge-green', 'extended' => 'b-badge-blue',
        'out_for_delivery' => 'b-badge-blue', 'picked' => 'b-badge-blue', 'in_progress' => 'b-badge-blue',
        'assigned' => 'b-badge-blue', 'ready' => 'b-badge-blue', 'packed' => 'b-badge-blue',
        'confirm_open' => 'b-badge-amber', 'waiting' => 'b-badge-amber', 'partially_received' => 'b-badge-amber',
        'cancelled' => 'b-badge-red', 'failed' => 'b-badge-red', 'rejected' => 'b-badge-red',
        'suspended' => 'b-badge-red', 'closed' => 'b-badge-grey', 'removed' => 'b-badge-red',
        'rescheduled' => 'b-badge-blue', 'reopened' => 'b-badge-amber', 'draft' => 'b-badge-grey',
        'expired' => 'b-badge-grey', 'converted' => 'b-badge-green', 'validated' => 'b-badge-blue',
    ];
    return $map[$status] ?? 'b-badge-grey';
}

/** Unit label map. */
function unit_label(string $unit): string
{
    return t('unit.' . $unit);
}

/** Render language switch dropdown */
function include_lang_switch(string $current): string
{
    $langs = ['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'];
    $out = '<i data-lucide="languages" class="text-muted"></i><select class="form-select form-select-sm b-langsel" onchange="location.href=this.value" aria-label="Language">';
    foreach ($langs as $code => $label) {
        $out .= '<option value="' . url('/lang/' . $code) . '"' . ($current === $code ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

