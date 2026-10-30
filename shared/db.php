<?php
declare(strict_types=1);

// ---------------------------------------------------------------
// Shared by the website and the management system: configuration,
// database connection, prepared-query helpers and HTML escaping.
// Include through the locator in each app (see README), never
// directly from a URL.
// ---------------------------------------------------------------

if (!defined('NUBIA_SHARED')) define('NUBIA_SHARED', __DIR__);

if (!is_file(NUBIA_SHARED . '/config.php')) {
    http_response_code(500);
    exit('Missing nubia-shared/config.php. Copy config.sample.php to config.php and add the database details.');
}

$GLOBALS['CONFIG'] = require NUBIA_SHARED . '/config.php';
$CONFIG = $GLOBALS['CONFIG'];

date_default_timezone_set($CONFIG['timezone'] ?? 'Africa/Nairobi');
if (!empty($CONFIG['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

/** A value from config.php, or the whole array when no key is given. */
function nubia_config(?string $key = null, $default = null)
{
    $c = $GLOBALS['CONFIG'] ?? [];
    if ($key === null) return $c;
    return $c[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', nubia_config('db_host'), nubia_config('db_name'));
        try {
            $pdo = new PDO($dsn, (string)nubia_config('db_user'), (string)nubia_config('db_pass'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET time_zone = '+03:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Could not connect to the database. Check the details in nubia-shared/config.php.');
        }
    }
    return $pdo;
}

/** Run a query with parameters and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function all(string $sql, array $params = []): array { return q($sql, $params)->fetchAll(); }
function one(string $sql, array $params = []): ?array { $r = q($sql, $params)->fetch(); return $r ?: null; }
function val(string $sql, array $params = []) { return q($sql, $params)->fetchColumn(); }

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/** URL-safe slug: "Kilimani Terrace, 2B" -> "kilimani-terrace-2b". */
function slugify(string $text, int $max = 80): string
{
    $s = mb_strtolower(trim($text), 'UTF-8');
    $s = strtr($s, ['’' => '', "'" => '', '&' => ' and ']);
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($t !== false) $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    if (strlen($s) > $max) $s = rtrim(substr($s, 0, $max), '-');
    return $s !== '' ? $s : 'item';
}
