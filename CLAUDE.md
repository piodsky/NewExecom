# Pyo Base System

A reusable PHP starting point extracted from IAMS (`../IAMS`): session auth,
role-based access control (per-role **and** per-account), a notification bell,
a self-service profile with avatar upload, and a sidebar/topbar shell. Clone
this folder to start a new project instead of building the login/roles/nav
plumbing from scratch again.

## Stack

Plain PHP + PDO (MySQL/MariaDB), no framework, no build step. Bootstrap 5 +
Font Awesome + jQuery + DataTables, all vendored locally in `vendor/` (no
CDN/internet dependency). Session-based auth (not JWT/tokens).

## Default login

Username `admin`, password `admin123`, role `super_admin`. **Change this
password immediately** via My Profile (top-right avatar menu) on any real
deployment — it's a known default sitting in `database/schema.sql`.

## Local setup

1. Create/import the database: the schema already ran once during setup
   (`database/schema.sql` against local MariaDB, db name `pyobasesystem_db`,
   user `root`, no password — see `.env`). To rebuild from scratch:
   `mysql -u root < database/schema.sql` (this DROPs and recreates every
   table — the seeded admin account included).
2. Point a browser at `/SystemsMISPYO/PurchaseOrder/` via XAMPP Apache.
   The root `index.php` redirects into `system/`, whose own `index.php`
   forwards to `login.php` or `dashboard.php` depending on session state.
3. `.env` holds DB credentials + `APP_NAME`. Change `APP_NAME`/`APP_FULL_NAME`
   first thing when starting a new project from this base — it flows into
   page titles, the sidebar brand, and the login screen automatically.

## Folder layout

The whole app lives in `system/`, matching how IAMS and PPMP are laid out
under the same document root. Only the root `index.php` redirect plus
non-web material (`CLAUDE.md`, `vb/` legacy sources, `tools/`) sit above it.

```
index.php              Redirects to system/index.php — the only web file at root
system/                The application (everything in the file map below)
tools/                 Crystal .rpt extractors + generated report specs (dev only)
vb/                    Legacy VB.NET sources — the spec of record, never served
```

`getBasePath()` in `config.php` returns `/SystemsMISPYO/PurchaseOrder/system`
and is what `requireAuth()` builds its login redirect from — if this folder
is ever moved or renamed, that function is the one place to change.

## File map (all paths relative to `system/`)

```
config.php          Session bootstrap, DB connection, ALL shared helpers (see below)
index.php            Loading screen -> redirects to login.php or dashboard.php
login.php            Login form (posts JSON to api/auth.php)
logout.php            Destroys session, redirects to login.php
sidebar.php           Left nav — included by every protected page. Add new nav
                        links here, gated by $moduleAccess like the existing ones.
topbar.php            Right side of the top bar: notification bell + account
                        dropdown. Do NOT put the profile modal's markup in here
                        (see the stacking-context note below).
profile_modal.php    The "My Profile" modal — included once at the END of
                        <body> on every page, deliberately outside .top-navbar.
dashboard.php / api/dashboard.php    Stat cards + recent activity feed
reports.php / api/reports.php        Example report over audit_logs — a
                        template for your own reports (filters -> table -> print/CSV)
settings.php / api/settings.php      User management (CRUD) + audit log viewer
access_levels.php / api/access_levels.php   Role matrix + per-account overrides
                        (super_admin only)
css/base.css           Every shared style. CSS variables at the top re-theme
                        the whole app.
database/schema.sql    Full fresh-install schema (drops + recreates everything)
uploads/avatars/       Where profile pictures land; .htaccess blocks executing
                        anything from this folder — keep that file if you ever
                        recreate the directory.
```

## Roles & access control

Three roles: `user` < `admin` < `super_admin` (`ROLE_RANK` in `config.php`).
Two independent layers, both defined in `config.php`:

- **`requireRole($roles)`** — hard gate, e.g. Access Levels is
  `requireRole(['super_admin'])` only.
- **`requireModule($key)`** — soft gate driven by the Access Levels matrix.
  `ACCESS_MODULES` in `config.php` is the master list of what's toggleable;
  currently just `reports` and `settings`. **When you add a new page,
  add its key/label to `ACCESS_MODULES` too**, or it'll never show up on the
  Access Levels screen for admins to restrict.

Access resolution order for any module key: per-account override
(`user_module_access`) > per-role default (`role_module_access`) > default
`true` if neither row exists. `getEffectiveModuleAccess($userId, $role)` is
the one function that does this merge — call it from `sidebar.php` (to hide
links) and from the top of each page/API (`requireModule('key')`) — both
are required; hiding a sidebar link alone does not block direct URL access.

Dashboard is intentionally **not** in `ACCESS_MODULES` — it's the redirect
target both `requireAuth()` failures and `requireModule()` failures land on,
so gating it risks a redirect loop.

## Adding a new page (the checklist)

1. Add the module key to `ACCESS_MODULES` in `config.php` if it should be
   individually toggleable (most real pages should be).
2. Create `yourpage.php` following the existing pattern exactly:
   ```php
   require_once 'config.php';
   requireAuth();
   requireModule('yourpage');
   $currentUser = getCurrentUser();
   $activePage  = 'yourpage';
   ```
   … then `<?php include 'sidebar.php'; ?>` for the nav, a `.top-navbar` div
   with `<?php include 'topbar.php'; ?>` on its right side, and
   `<?php include 'profile_modal.php'; ?>` right before `</body>` (not
   inside `.top-navbar` — see the note below).
3. Add the matching `<?php requireModule('yourpage'); ?>` guard at the top of
   `api/yourpage.php` too — the sidebar link hides the page but does not by
   itself block someone hitting the API directly.
4. Add a `<a href="yourpage.php" class="sidebar-link ...">` entry to
   `sidebar.php`, gated the same way the Reports/Settings links already are.
5. If the feature should notify anyone, call
   `notifyRoles(['admin','super_admin'], 'Title', 'message', 'yourpage.php')`
   from wherever the triggering event happens (see how `IAMS/system/api/requests.php`
   does this for "new request submitted" — same pattern, drop it in your
   create/update handler).

## Known gotcha: don't nest modals inside `.top-navbar`

`.top-navbar` has `position: sticky` + `z-index: 100`, which creates its own
CSS stacking context. A Bootstrap modal nested inside it gets visually and
functionally trapped below Bootstrap's dynamically-appended
`.modal-backdrop` (`z-index: 1050`, appended straight to `<body>`) — the
modal renders but silently swallows every click. This is exactly why
`profile_modal.php` is its own include, added at the end of `<body>` rather
than living inside `topbar.php`. Any new modal that's reachable from the
topbar needs the same treatment.

## Notifications

`notifications` table is role-broadcast (one row can target many users of a
role); `notification_reads` tracks per-user read state so each recipient can
dismiss independently without affecting anyone else. Nothing calls
`notifyRoles()` yet in this base — wire it into your own feature's
create/update handlers. The bell (`topbar.php`) already polls
`api/notifications.php` and needs no changes to pick up new notification
sources.

## Profile / avatars

`users.full_name` is a **derived, denormalized** column — always recomputed
from `first_name + middle_name + last_name + suffix` on every save (see
`api/profile.php` and `api/settings.php`). Nothing should ever write to
`full_name` directly; everything that displays a user's name (sidebar,
topbar, session, audit log, My Profile) reads `full_name`, so keeping it in
sync there is what makes that work everywhere else without extra joins.

Avatars: validated by real image content (`getimagesize()` + MIME
whitelist), not just file extension, capped at 2MB, renamed to
`user_{id}_{timestamp}.{ext}` (never trust the original filename), stored in
`uploads/avatars/`. That folder's `.htaccess` blocks executing anything as
PHP — if you ever recreate the folder, recreate the `.htaccess` too.

## Security notes carried over from IAMS

- `jsonResponse()` encodes with `JSON_INVALID_UTF8_SUBSTITUTE` — protects
  against any external/legacy data source with invalid UTF-8 bytes silently
  turning an API response blank (`json_encode()` returns `false` on invalid
  UTF-8 otherwise, and `echo false` prints nothing).
- Session cookie name is `pbs_session` (set in `config.php`) specifically so
  this app doesn't collide with sibling projects under the same
  `SystemsMISPYO` document root sharing the default `PHPSESSID` cookie.
- All SQL goes through PDO prepared statements — never string-concatenate
  user input into a query.
