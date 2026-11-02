<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Loaded at the top of every page: shared config and database, session,
// security helpers, formatting helpers.
// ---------------------------------------------------------------

// Configuration, database connection, q()/one()/all()/val() and e()
// come from the shared folder used by both the website and this system.
require __DIR__ . '/shared.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Session
session_name('nubia_sess');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Sign out after 2 hours without activity
if (isset($_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > 7200) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION['last_seen'] = time();

// ---------- escaping and formatting ----------

function money($n, bool $prefix = true): string
{
    $s = number_format((float)$n, 2);
    return $prefix ? 'KES ' . $s : $s;
}

function fdate(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }

function period_label(string $p): string { return date('F Y', strtotime($p . '-01')); }

function current_period(): string { return date('Y-m'); }

function valid_period(?string $p): string
{
    return ($p && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p)) ? $p : current_period();
}

function settings(): array
{
    static $s = null;
    if ($s === null) $s = one('SELECT * FROM settings WHERE id = 1') ?? [];
    return $s;
}

// ---------- requests ----------

function post(string $k, $default = '') { return isset($_POST[$k]) ? (is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k]) : $default; }
function get(string $k, $default = '') { return isset($_GET[$k]) ? (is_string($_GET[$k]) ? trim($_GET[$k]) : $_GET[$k]) : $default; }
function num($v): float { return round((float)str_replace([',', ' '], '', (string)$v), 2); }
function nullable($v) { return ($v === '' || $v === null) ? null : $v; }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function redirect(string $to): void { header('Location: ' . $to); exit; }

function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'][] = [$type, $msg]; }

function take_flash(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }

function check_csrf(): void
{
    if (!is_post()) return;
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('This form expired. Go back, reload the page and try again.');
    }
}

// ---------- auth ----------

function user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (user()['role'] ?? '') === 'admin'; }

function require_login(): void
{
    if (!user()) redirect('login.php');
    // Removed or changed accounts take effect immediately
    $u = one('SELECT id, name, email, role, active FROM users WHERE id = ?', [user()['id']]);
    if (!$u || !$u['active']) {
        $_SESSION = [];
        redirect('login.php');
    }
    $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
    check_csrf();
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('Only an administrator can do that.', 'error');
        redirect('index.php');
    }
}

/** Staff may only change listings that are not yet live; otherwise an edit would skip the publishing step. */
function can_edit_listing(array $l): bool { return is_admin() || in_array($l['status'], ['draft', 'review'], true); }

function log_action(string $action): void
{
    q('INSERT INTO activity_log (user_id, action) VALUES (?, ?)', [user()['id'] ?? null, mb_substr($action, 0, 255)]);
}

// ---------- accounting ----------
// One rule everywhere: balance = everything invoiced − everything paid,
// for a client in a unit. Every screen uses these functions so the
// numbers always agree.

/** Outstanding balance for a client in a unit, optionally up to (not including) a period. */
function balance(int $unitId, int $clientId, ?string $beforePeriod = null): float
{
    if ($beforePeriod) {
        $inv = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE unit_id=? AND client_id=? AND period < ?', [$unitId, $clientId, $beforePeriod]);
        $pay = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id=? AND client_id=? AND paid_on < ?', [$unitId, $clientId, $beforePeriod . '-01']);
    } else {
        $inv = (float)val('SELECT COALESCE(SUM(total),0) FROM invoices WHERE unit_id=? AND client_id=?', [$unitId, $clientId]);
        $pay = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE unit_id=? AND client_id=?', [$unitId, $clientId]);
    }
    return round($inv - $pay, 2);
}

/** All client/unit accounts that have ever been invoiced or paid, with balances. */
function accounts(?int $propertyId = null): array
{
    $where = $propertyId ? 'WHERE u.property_id = ' . (int)$propertyId : '';
    return all("
        SELECT a.unit_id, a.client_id, u.unit_no, p.id AS property_id, p.name AS property, c.name AS client, c.phone, c.email,
               COALESCE((SELECT SUM(total) FROM invoices i WHERE i.unit_id=a.unit_id AND i.client_id=a.client_id),0) AS invoiced,
               COALESCE((SELECT SUM(amount) FROM payments y WHERE y.unit_id=a.unit_id AND y.client_id=a.client_id),0) AS paid,
               (SELECT MAX(paid_on) FROM payments y WHERE y.unit_id=a.unit_id AND y.client_id=a.client_id) AS last_paid,
               (u.client_id = a.client_id) AS is_current
        FROM (SELECT unit_id, client_id FROM invoices UNION SELECT unit_id, client_id FROM payments) a
        JOIN units u ON u.id = a.unit_id
        JOIN properties p ON p.id = u.property_id
        JOIN clients c ON c.id = a.client_id
        $where
        ORDER BY p.name, u.unit_no");
}

function next_invoice_no(string $period): string
{
    $prefix = 'INV-' . str_replace('-', '', $period) . '-';
    $last = val('SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $n = $last ? ((int)substr($last, strlen($prefix)) + 1) : 1;
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/** Kenyan phone number in international form for WhatsApp links. */
function wa_number(?string $phone): string
{
    $d = preg_replace('/\D/', '', (string)$phone);
    if (str_starts_with($d, '0')) $d = '254' . substr($d, 1);
    if (strlen($d) === 9) $d = '254' . $d;
    return $d;
}

function wa_link(?string $phone, string $text): string
{
    return 'https://wa.me/' . wa_number($phone) . '?text=' . rawurlencode($text);
}

require __DIR__ . '/layout.php';
