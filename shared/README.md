# nubia-shared

Settings and code used by both the website (nubiakcestates.com) and the management system (system.nubiakcestates.com).

Upload this folder to the top of your Hostinger account, **outside** `public_html`, for example `/home/u123456789/nubia-shared`. Then copy `config.sample.php` to `config.php` and fill it in.

- `config.php`: database details, site and system addresses, uploads folder. Never upload this to `public_html`.
- `db.php`: database connection and the `q()`, `one()`, `all()`, `val()`, `e()` and `slugify()` helpers.
- `images.php`: photo uploads (checks the file, saves WebP at 1600px, 800px and 400px wide, drops metadata).

Listing and blog queries will be added here in the next steps.
