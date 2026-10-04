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

## How to Run

1. Copy the `CampusFind` folder into XAMPP's `htdocs` directory.
2. Start Apache and MySQL from the XAMPP control panel.
3. Import `database/campusfind.sql` into MySQL (for example, via phpMyAdmin). The script creates the `campusfind` database and tables and seeds demo records. It does not drop existing tables.
4. Configure the database connection (see below).
5. Open `http://localhost/CampusFind/` in your browser.

The browser must access the project through Apache; opening the HTML files directly as `file://` will not work because the PHP APIs require a web server.

### Database configuration

The connection settings are read from the environment variables below. Defaults are `localhost`, `campusfind`, `root`, and an empty password, which match a default XAMPP installation.

| Variable      | Default       | Purpose                     |
| ------------- | ------------- | --------------------------- |
| `DB_HOST`     | `localhost`   | MySQL server host           |
| `DB_NAME`     | `campusfind`  | Database name               |
| `DB_USER`     | `root`        | MySQL user                  |
| `DB_PASS`     | *(empty)*     | MySQL password              |

With XAMPP you can set these in Apache's environment (for example, `SetEnv DB_PASS yourpassword` in `httpd.conf` or a virtual host), or simply edit the defaults in `config/database.php`. See `.env.example` for a template.

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

## Project structure

```text
├── admin/            Administrator HTML pages
├── api/              PHP JSON endpoints
├── assets/           CSS, JavaScript, and images
├── config/           Database connection
├── database/         MySQL schema and seed data
├── docs/             Project documentation
├── includes/         Shared PHP helpers
├── uploads/items/    Uploaded item images
└── *.html            Public frontend pages
```

## Troubleshooting

- **Blank page or Apache errors:** check the Apache error log and confirm PHP 7.4+ is running with the PDO MySQL and Fileinfo extensions enabled.
- **API requests fail with a database error:** confirm MySQL is running, the `campusfind` database was imported, and the credentials in `config/database.php` (or the environment variables) are correct.
- **Login fails with seeded accounts:** the demo passwords are `password`; re-import `database/campusfind.sql` if the users table was modified.
- **Uploads fail:** ensure `uploads/items/` is writable by the web server and the uploaded file is a JPG, PNG, or WEBP image no larger than 5 MB.
