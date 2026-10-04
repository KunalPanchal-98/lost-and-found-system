# CampusFind

CampusFind is a campus lost-and-found application. The user interface is static HTML5/CSS3 with AngularJS 1.x and vanilla JavaScript. PHP is used only for JSON APIs, sessions, authorization, uploads, and PDO/MySQL business logic.

```text
HTML + CSS + AngularJS + JavaScript
                  ↓ AJAX / JSON
               PHP APIs
                  ↓ PDO
                MySQL
```

No Node.js, npm, frontend build step, or frontend framework is required.

## Requirements

- XAMPP (Apache, PHP 7.4 or later, MySQL 5.7 or later)
- PHP PDO MySQL and Fileinfo extensions enabled

## Install and run

1. Copy `CampusFind` into XAMPP's `htdocs` directory.
2. Start Apache and MySQL from the XAMPP control panel.
3. Import `database/campusfind.sql` into MySQL. The script creates the `campusfind` database and tables and seeds demo records. It no longer drops existing tables.
4. If necessary, configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the Apache/PHP environment. Defaults are `localhost`, `campusfind`, `root`, and an empty password.
5. Visit `http://localhost/CampusFind/`.

The browser must access the project through Apache; opening the HTML files directly as `file://` will not work because the PHP APIs require a web server.

## Demo accounts

- Admin: `admin@campusfind.edu` / `password`
- Demo users: use any seeded campus user email / `password`

Change or remove these demo accounts before deployment. New account passwords are stored using PHP `password_hash`; the demo SQL seeds also use a bcrypt hash.

## Pages and API

The public HTML frontend is at the project root; administrator HTML pages are in `admin/`. AngularJS requests JSON from:

- `api/auth.php` — session, registration, login, logout, and profile
- `api/items.php` — public item search/details, report submission, options, dashboard, and rule-based match scoring
- `api/claims.php` — claimant submission and claim history
- `api/notifications.php` — notifications and read state
- `api/admin.php` — role-protected administration and dashboard operations

All mutating endpoints require the session-bound CSRF token provided by `api/auth.php?action=session`. Administrator authorization is checked in PHP, not inferred from frontend state.

## Data model

MySQL tables: `users`, `categories`, `locations`, `items`, `claims`, `notifications`, and `reports`. The existing schema is retained with non-destructive `CREATE TABLE IF NOT EXISTS` statements and foreign-key constraints. Item uploads accept JPEG, PNG, or WEBP up to 5 MB; files are stored with random names in `uploads/items/`.

More detail is available in `docs/college-project-documentation.md`.
