# Nubia KC Estates

Private repo. Website, management system and shared code.

- `site/` → nubiakcestates.com (`public_html/`)
- `system/` → system.nubiakcestates.com (`public_html/system/`)
- `shared/` → `nubia-shared/` outside the web root

## First-time setup
1. Install Laragon (Full) on Windows. Start All.
2. Put this repo in `C:\laragon\www\nubia`. Laragon creates `http://nubia.test`.
3. Copy `shared/config.sample.php` to `shared/config.php` and fill it in (see CLAUDE.md, Local setup).
4. phpMyAdmin (Laragon > Database): create database `nubia`, import `system/install/database.sql`, then run each file in `system/install/migrations/` in order.
5. Open `http://nubia.test/system/` and create the first administrator.
6. In the repo folder run `claude` to start Claude Code. It reads `CLAUDE.md`.

## Deploying
Push to `main`. The GitHub Action uploads `site/`, `system/` and `shared/` to Hostinger over FTP.
Set `FTP_HOST`, `FTP_USER`, `FTP_PASS` in GitHub > Settings > Secrets and variables > Actions.
`shared/config.php` and `uploads/` are never uploaded or committed; create `config.php` on the server once by hand.

Database changes are never deployed automatically. After a push that adds a migration, run it in Hostinger phpMyAdmin.
