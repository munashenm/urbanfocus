# AGENTS.md

Urban Focus is a Laravel 11 (PHP 8.2+) ecommerce storefront + admin. It is a single service (no separate frontend build — views are Blade + Bootstrap 5, there is no `package.json`). See `README.md` for the product overview and cPanel deployment guide.

## Cursor Cloud specific instructions

The base VM snapshot already has PHP 8.3 (CLI + `mbstring`, `xml`, `curl`, `zip`, `gd`, `sqlite3`, `mysql`, `bcmath`, `intl`) and Composer installed, plus a local `.env` and a seeded SQLite database. The startup update script only runs `composer install`.

### Local dev configuration (already applied in the snapshot)

- The app defaults to MySQL/MariaDB (for cPanel prod), but local dev here uses **SQLite** at `/workspace/database/database.sqlite`. This is set in the gitignored `.env` (`DB_CONNECTION=sqlite`, absolute `DB_DATABASE` path). `.env` also uses `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, and `MAIL_MAILER=log`.
- Gotcha: `.env.example` contains an unquoted value (`BOBSHOP_LOCATION=South Africa`) that breaks dotenv parsing. If you regenerate `.env` from the example, quote that value (`"South Africa"`) or `php artisan key:generate` and every artisan command will fail with "Failed to parse dotenv file".
- If the SQLite file or `.env` is ever missing (e.g. fresh checkout without the snapshot), recreate with: `cp .env.example .env` (then fix the quote above), set SQLite in `.env`, `touch database/database.sqlite`, `php artisan key:generate`, `php artisan migrate --seed`, `php artisan storage:link`.

### Run

- Dev server: `php artisan serve --host=0.0.0.0 --port=8000` (run in a tmux session). Homepage `/`, `/shop`, `/product/{slug}`, `/cart`, `/login` all serve; `/admin` 302-redirects to `/login` until authenticated.
- Seeded admin login: `admin@urbanfocus.co.za` / `ChangeMe123!`.
- Sample data: seeder creates 1 admin user, 6 sample products, ~99 categories. Product images are not seeded, so listings show the "Urban Focus" placeholder image — this is expected, not a bug.

### Lint / Test

- Lint: `./vendor/bin/pint` (add `--test` to check without modifying). Note: the existing codebase currently has many pre-existing Pint style violations and `deploy/merge-categories.php` has a pre-existing parse error — these are not caused by env setup.
- Tests: `php artisan test` (PHPUnit; config in `phpunit.xml` forces SQLite `:memory:`, so tests do not touch the dev DB). As of setup, 72 pass and 8 fail. The 8 failures are pre-existing business-logic assertion mismatches in `Unit` tests (`BobShopFeedTest`, `CatalogFilterServiceTest`, `CategoryMapperServiceTest`, `ProductImportServiceTest` — category-mapping / feed content), unrelated to the environment. All DB-backed `Feature` tests pass.

### Payments / external services

Paystack, mail SMTP, and various social/marketing integrations are configured via `.env` and are disabled/keyless by default. Checkout works without real Paystack keys (tests cover the redirect + retry paths); no external credentials are required for local development.
