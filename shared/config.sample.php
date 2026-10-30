<?php
// Nubia KC Estates: settings shared by the website and the management system.
// Copy this file to config.php (in this same nubia-shared folder) and fill in
// the database details from hPanel > Databases > MySQL Databases.
// This folder must sit OUTSIDE public_html, e.g. /home/u123456789/nubia-shared

return [
    'db_host' => 'localhost',
    'db_name' => 'u000000000_nubia',
    'db_user' => 'u000000000_nubia',
    'db_pass' => 'CHANGE-ME',

    // Africa/Nairobi keeps dates and billing months correct.
    'timezone' => 'Africa/Nairobi',

    // Public addresses (no trailing slash).
    'site_url'   => 'https://nubiakcestates.com',
    'system_url' => 'https://system.nubiakcestates.com',

    // Where uploaded photos are saved, and the address they are served from.
    // uploads_dir is the full server path to public_html/uploads of the WEBSITE.
    // Find the path in hPanel > File Manager (shown at the top of the window).
    'uploads_dir' => '/home/u000000000/domains/nubiakcestates.com/public_html/uploads',
    'uploads_url' => 'https://nubiakcestates.com/uploads',

    // Set to true only while fixing a problem. Shows PHP errors on screen.
    'debug' => false,
];
