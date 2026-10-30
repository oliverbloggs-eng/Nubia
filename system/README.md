# Nubia KC Estates Management System

Plain PHP 8 and MySQL. No frameworks, no outside services, no monthly fees. Runs on any Hostinger shared plan.

## Install on Hostinger (about 20 minutes)

The website (nubiakcestates.com) and this system (system.nubiakcestates.com) share one database and one settings folder, **nubia-shared**, which sits outside the web root so it can never be opened in a browser.

Folder layout in hPanel > File Manager (your account number replaces u123456789):

```
/home/u123456789/
├── nubia-shared/                       ← database settings + shared code (not public)
└── domains/nubiakcestates.com/public_html/
    ├── index.html, about/, …            ← the website
    ├── uploads/                         ← listing and blog photos (no scripts can run here)
    └── system/                          ← this system (system.nubiakcestates.com)
```

1. **Create the subdomain.** hPanel > Domains > Subdomains: create `system.nubiakcestates.com`. Note the folder Hostinger gives it (usually `public_html/system`).
2. **Create the database.** hPanel > Databases > MySQL Databases: create a database and a user with a strong password. Note the database name, user name and password (Hostinger adds a prefix like `u123456789_`).
3. **Import the tables.** Open phpMyAdmin for that database, choose **Import**, select `install/database.sql` and click **Import**. (Already installed? Run the files in `install/migrations/` instead, in number order.)
4. **Upload the shared folder.** In File Manager go to the top folder (`/home/u123456789`, one level above `domains`) and upload `nubia-shared` there. Copy `config.sample.php` to `config.php` in that folder and fill in the database details from step 2 and the `uploads_dir` path (File Manager shows the full path at the top).
5. **Upload the system.** Open the subdomain folder from step 1, upload the system zip and extract it there. The folder should contain `index.php` directly, not a subfolder.
6. **Create the uploads folder** for the website: `public_html/uploads`, with the `.htaccess` file supplied in the website files (it stops scripts running there).
7. **Turn on SSL** for the subdomain (hPanel > Security > SSL).
8. **Create the first administrator.** Open `https://system.nubiakcestates.com/`. The first visit shows a setup screen. After creating the account you can delete `setup.php` (it stops working once any account exists anyway).
9. **Set company details.** Sign in, open **Company settings**, and check the address, phone, email, website, rent due day and payment instructions. These print on every invoice, receipt and statement.

**Moving an existing install to the shared folder:** do step 4 using the details from your old `config.php`, upload the new system files, then delete the old `config.php` from the system folder.

If the system says it cannot find nubia-shared, add this line to the system's `.htaccess` with your real path: `SetEnv NUBIA_SHARED /home/u123456789/nubia-shared`

Requirements: PHP 8.0 or newer with the GD extension (hPanel > Advanced > PHP Configuration), MySQL 5.7+ or MariaDB 10.3+.

## Monthly routine

1. **Monthly billing** (start of the month): choose the month, check the list, click **Create invoices**. Each unit can only be billed once per month, so running it twice is safe.
2. **Send invoices**: on the same page, **Send** opens WhatsApp with the invoice message filled in; **Invoice** opens a printable copy (Print > Save as PDF to email it).
3. **Payments**: record each M-Pesa or bank payment with its reference. The same reference cannot be entered twice.
4. **Arrears**: see who owes and send WhatsApp reminders.
5. **Owner statements** (month end): pick the property and month, print to PDF and send to the owner. Collected rent, less the management fee and completed repair costs, gives the amount to pay out.

## How the numbers work

Every screen uses one rule: **balance = everything invoiced − everything paid** for a client in a unit. The dashboard, units list, arrears, ledgers and statements therefore always agree.

- **Billed this month**: invoices for the current month.
- **Collected this month**: payments dated in the current month (including arrears paid).
- **Arrears**: the total of all accounts that owe money, across all months.
- **Units sold on a payment plan**: set the category to Sale and enter the monthly instalment. It is billed each month like rent.
- **Extra charges** (water, late fee, discount): use **Extra charge** on an invoice in Monthly billing.

## Roles

- **Administrator**: everything, plus deleting records, company settings and staff accounts.
- **Staff**: day-to-day work (properties, units, clients, billing, payments, repairs, statements). Staff cannot delete.

Records with history (units with invoices, clients with payments) cannot be deleted at all. Mark them Dormant instead, so the books stay complete.

## Managing data in phpMyAdmin

All data lives in ordinary tables you can browse, search and export in phpMyAdmin:

| Table | Holds |
|---|---|
| `properties` | Buildings, owner, management fee % |
| `units` | Units, rent, service charge, status, current client |
| `clients` | Tenants, buyers and owners |
| `invoices` | Monthly charges (one per unit per month) |
| `payments` | Money received |
| `maintenance` | Repair jobs and costs |
| `users` | Staff sign-ins (passwords are hashed) |
| `settings` | Company details on documents |
| `activity_log` | Who did what, and when |

**Backups:** in phpMyAdmin choose the database, then **Export** > Quick > SQL, at least once a month. Hostinger also keeps automatic backups (hPanel > Files > Backups).

Edit data through the system where possible. Changing invoice or payment rows directly in phpMyAdmin changes balances immediately.

## Security built in

- Passwords hashed with PHP's `password_hash`; sign-in locks for 15 minutes after 5 failed attempts.
- Every form is protected against cross-site request forgery; all database queries use prepared statements.
- Sessions expire after 2 hours of inactivity; removed staff are signed out on their next click.
- Database settings live in `nubia-shared/config.php`, outside the web root. `inc/` and `install/` are blocked from the web by `.htaccess`; HTTPS is forced.
- Uploaded photos are checked, re-saved as WebP (no metadata kept) and stored in a folder where scripts cannot run.
- Pages carry `noindex`, so search engines do not list the system.

## Replacing the logo

The system uses `assets/mark.svg`. Replace it with the company logo (keep the same file name, square format) and it appears in the sidebar, sign-in page and all printed documents.
