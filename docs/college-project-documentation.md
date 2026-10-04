# CampusFind: College Project Documentation

## Abstract

CampusFind centralizes campus lost-and-found reports, item discovery, ownership claims, and administration. Students and staff can report missing or found belongings, search existing reports, and submit identifying details for a claim. Administrators review reports and claims and maintain categories, locations, users, and item statuses.

## Technology and architecture

- **Frontend:** Static HTML5, CSS3, vanilla JavaScript, and AngularJS 1.x
- **Backend:** PHP 7.4+, PHP sessions, JSON APIs, and PDO
- **Database:** MySQL
- **Runtime:** Apache through XAMPP; no Node.js or build system

The frontend never renders through PHP. AngularJS sends asynchronous requests to PHP endpoints; PHP validates and authorizes requests, accesses MySQL through prepared PDO statements, then responds with JSON.

```text
HTML/CSS/AngularJS/JavaScript → AJAX/JSON → PHP API → PDO → MySQL
```

## Functional modules

- Account registration, login, session status, logout, and profile editing
- Lost and found item reports with server-validated image uploads
- Keyword and structured item search
- Dynamically loaded item details and weighted rule-based matching (not AI)
- Claims containing a unique feature, approximate time and location, and additional details
- Claim review statuses: Pending, Under Review, Approved, Rejected, Completed
- Per-user notifications with unread counts and read controls
- Role-protected administration of users, items, claims, categories, locations, and reports

## Security and validation

Passwords are hashed with `password_hash` and checked using `password_verify`; legacy plaintext credentials are upgraded when successfully used. Session cookies are HTTP-only and same-site. Mutating API requests require a session CSRF token. PHP enforces authentication and administrator permissions independently from frontend visibility. SQL values use prepared statements. Uploaded images are checked by MIME type and size, assigned random filenames, and isolated from PHP execution.

## Database entities

- `users`: campus accounts, roles, and active state
- `categories`, `locations`: item classification and campus location reference data
- `items`: lost/found reports
- `claims`: claimant verification details and workflow status
- `notifications`: user-specific status and match notifications
- `reports`: report review records

## Run locally

1. Copy the project into XAMPP's `htdocs`.
2. Start Apache and MySQL.
3. Import `database/campusfind.sql`.
4. Open `http://localhost/CampusFind/`.

The database connection reads `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`; local defaults are `localhost`, `campusfind`, `root`, and an empty password.

## Test plan

Validate registration/login/logout, protected user and admin requests, lost/found submissions, image validation, item search and filters, detail and match calculations, claim submission and all status transitions, notification creation/read state, admin CRUD and status updates, responsive layouts, database constraints, and JSON error responses.

## Future improvements

Institution-managed email verification, asynchronous communication with campus security, audit history, and optional multi-campus support can be added without changing the frontend/backend boundary.
