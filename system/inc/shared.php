<?php
declare(strict_types=1);

// Finds the nubia-shared folder (database settings and shared code) that
// sits outside the web root, and loads it. It looks in each parent folder
// of this one, so it works wherever Hostinger puts the subdomain.
// To point somewhere else, set NUBIA_SHARED in hPanel or in .htaccess:
//   SetEnv NUBIA_SHARED /home/u123456789/nubia-shared

if (!defined('NUBIA_SHARED')) {
    $found = null;
    $env = getenv('NUBIA_SHARED') ?: ($_SERVER['NUBIA_SHARED'] ?? '');
    if ($env && is_file(rtrim($env, '/') . '/db.php')) {
        $found = rtrim($env, '/');
    } else {
        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            $parent = dirname($dir);
            if ($parent === $dir) break;
            $dir = $parent;
            if (is_file($dir . '/nubia-shared/db.php')) { $found = $dir . '/nubia-shared'; break; }
            if (is_file($dir . '/shared/db.php')) { $found = $dir . '/shared'; break; } // local repo layout
        }
    }
    if ($found === null) {
        http_response_code(500);
        exit('Missing the nubia-shared folder. Upload it outside public_html (see README, step 4).');
    }
    define('NUBIA_SHARED', $found);
}

require_once NUBIA_SHARED . '/db.php';
