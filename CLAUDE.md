# Nubia KC Estates — brief for Claude Code

Read this file at the start of every session. Follow it over your own defaults.

## The company
Nubia KC Estates, a real-estate company in Kenya (Nairobi, Kilimani, Westlands, Ngong Road, Thika, Juja, Murang'a). Sells, lets and manages property. Currency KES. Timezone Africa/Nairobi.

## Repo layout
- `site/` — public website, deployed to `public_html/` (nubiakcestates.com). 9 pages, live, SEO done. Do not change page design, copy, meta tags, sitemap or URLs unless asked.
- `system/` — management system, deployed to `public_html/system/` (system.nubiakcestates.com). Plain PHP 8 + MySQL.
- `shared/` — `db.php`, `images.php`, `config.php` (not in Git). Deployed to `nubia-shared/` OUTSIDE the web root. Both site and system include it.
- `.github/workflows/deploy.yml` — FTP deploy on push to `main`.

## Local setup (Laragon, Windows)
- Repo folder in `C:\laragon\www\nubia`. Local address `http://nubia.test`.
- Copy `shared/config.sample.php` to `shared/config.php`; local DB `nubia`, user `root`, empty password; `uploads_dir` = `C:/laragon/www/nubia/site/uploads`, `uploads_url` = `http://nubia.test/site/uploads`, `site_url` = `http://nubia.test/site`, `system_url` = `http://nubia.test/system`, `debug` = true.
- Import `system/install/database.sql`, then each `system/install/migrations/*.sql` in number order, in phpMyAdmin.

## Hard rules
1. **Stack:** plain PHP 8, MySQL/MariaDB, PDO prepared statements only. No frameworks, no Composer, no npm, no outside services or paid APIs. Must run on Hostinger shared hosting.
2. **Database changes are manual.** Never run SQL against a database yourself and never auto-migrate in PHP. Write a new numbered file in `system/install/migrations/` (`003_...sql`), make it safe to re-run, and also update `database.sql` for fresh installs. Tell me to run it in phpMyAdmin.
3. **Accounting rule:** balance = everything invoiced − everything paid, for a client in a unit. Use `balance()` / `accounts()` in `system/inc/bootstrap.php`. Never store a running balance column. Never edit or delete invoices/payments that have history; mark records Dormant instead.
4. **Security:** every POST form uses `csrf_field()` and pages call `require_login()` (which checks CSRF). Escape all output with `e()`. Uploads only through `nubia_image_save()` (WebP, metadata stripped, stored in `uploads/` where scripts cannot run). Secrets only in `shared/config.php`.
5. **Roles:** `admin` can do everything. `staff` cannot delete, cannot publish, cannot see Users or Company settings. Check on the server (`is_admin()`, `require_admin()`), not only by hiding buttons.
6. **Log** every create, edit, publish and delete with `log_action()`.
7. **Never commit** `shared/config.php`, uploads, database exports or passwords.
8. Small commits with plain messages. Run `php -l` on changed files before committing. Test at `http://nubia.test` before pushing to `main`.

## Brand rules
- Colours: navy, gold and ivory from the existing website (`site/styles.css`). Reuse those values; do not invent new colours.
- Logo files are in `site/assets/` (`logo-gold`, `mark-gold`). The system uses `system/assets/mark.svg`.
- Copy: plain, sentence case, short. No emoji. Money as `KES 1,250,000.00` via `money()`. Dates `30 Oct 2026` via `fdate()`.
- System screens follow the admin dashboard prototype: left sidebar, quick actions, key figures, "Needs attention". Reuse `system/assets/app.css` and `inc/layout.php`.

## Roadmap
1. ~~Website live~~ (done)
2. ~~System skeleton: users, login lockout, CSRF, roles, properties, units, clients, invoices, payments, arrears, maintenance, activity log~~ (done)
3. **Listings — current step** (below)
4. Website reads listings: `find-property/` and a page per listing (`/property/{slug}`), with sitemap entries
5. Blog: editor, photos, draft → review → published (staff submit, admin publishes); website reads posts
6. Enquiries: website contact and listing forms save to an `enquiries` table; inbox in the system with WhatsApp/email reply
7. Dashboard: live key figures, quick actions, Needs attention
8. Hardening: backups note, error pages, final security review

## Step 3: Listings
Goal: staff add and edit property listings with photos; admin publishes them so the website can show them.

Database: `system/install/migrations/002_listings.sql` (tables `listings`, `listing_photos`). Already written; add the same to `database.sql`.

Build:
- `shared/listings.php` — queries used by both site and system: `listing_find($id)`, `listing_by_slug($slug)` (published only), `listings_public($filters)` (published, featured first, newest first; filters purpose, type, area, min/max price, bedrooms), `listing_photos($id)`, `listing_unique_slug($title, $ignoreId)`.
- `system/listings.php` — list with search and filters (status, purpose, area), status chips, Featured star, cover thumbnail, price in KES.
- `system/listing.php` — add/edit form: title, purpose, type, area, address, price, bedrooms, bathrooms, size, summary (max 300), description, features (one per line), optional link to property/unit, Featured. Photo panel: upload several, set cover, reorder, alt text, delete. Slug auto from title, editable, unique.
- Status flow: `draft` → `review` (staff "Submit for review") → `published` (admin only, sets `published_at`). Admin can also mark `let`, `sold`, `archived`. Staff cannot publish, archive or delete.
- Delete (admin only): removes photos from disk with `nubia_image_delete()` and the rows.
- Add "Listings" to the sidebar in `inc/layout.php`; show a count of listings in review to admins.

Done when: I can add a listing with 5 photos locally, submit it as staff, publish it as admin, see it in phpMyAdmin, and staff cannot publish or delete even by posting the form directly.

## How to work with me
- I am not a developer. Explain what to click in plain words.
- Before large changes, list the files you will touch and wait for "go".
- After each change, tell me how to test it at `http://nubia.test` and whether a migration needs running.
