# Repository Guidelines

## Project Structure & Module Organization
This repository is a Laravel 12 application for supplier, purchasing, and requisition workflows. Core backend code lives in `app/`, with HTTP controllers in `app/Http/Controllers`, request validation in `app/Http/Requests`, domain services in `app/Services`, and Livewire components in `app/Livewire`. Blade views are under `resources/views`, route definitions under `routes/`, database migrations, factories, and seeders under `database/`, and browser-facing assets in `public/`. Tests are split into `tests/Feature` and `tests/Unit`. Project notes and implementation specs live in `docs/`.

## Build, Test, and Development Commands
- `composer install` installs PHP dependencies.
- `npm install` installs Vite frontend tooling.
- `composer dev` starts the full local stack: Laravel server, queue listener, log tailing, and Vite.
- `npm run dev` runs only the Vite asset watcher.
- `npm run build` creates production assets.
- `php artisan migrate --seed` applies schema changes and seeds baseline data.
- `composer test` clears config and runs the PHPUnit suite through `php artisan test`.

## Coding Style & Naming Conventions
Follow `.editorconfig`: UTF-8, LF endings, spaces for indentation, and 4-space indent by default. Format PHP with `./vendor/bin/pint`. Use PSR-4 class names and Laravel naming conventions: singular Eloquent models (`Supplier`), descriptive controllers (`SupplierController`), and request classes prefixed by intent (`SaveBudgetMovementRequest`). Keep Blade partials in feature folders such as `resources/views/users/staff/partials`.

## Testing Guidelines
Write request, UI flow, and authorization tests in `tests/Feature`; isolate pure logic in `tests/Unit`. Name tests after the behavior under test, matching existing files such as `EmployeePromoteTest.php` and `AllowedEmailDomainTest.php`. The test environment uses in-memory SQLite (`phpunit.xml`), so new tests should be self-contained and seed only what they need. Run `composer test` before opening a PR.

## Commit & Pull Request Guidelines
Recent history favors short conventional prefixes like `feat:`, `refactor:`, and `docs:`. Keep commits focused and imperative, for example `feat: add supplier delivery evidence upload`. PRs should include a concise description, impacted routes or modules, migration or seeding notes when applicable, linked issue or task context, and screenshots for Blade or Livewire UI changes.

## Security & Configuration Tips
Do not commit secrets from `.env`; use `.env.example` as the template. When adding integrations or background jobs, document required environment variables and queue behavior in the PR. Review `config/` defaults before changing mail, queue, or permission settings.

## Database Safety
Protect operational and shared databases. Codex may perform intentional, scoped data writes requested by the user, such as `INSERT`, `UPDATE`, ordinary additive migrations, or a specific seeder/importer. Before a write, inspect and report the effective connection and database name; do not proceed if the target is shared, production-like, unknown, or configuration makes it uncertain.

- Never run `php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan db:wipe`, `php artisan schema:dump --prune`, or equivalent destructive SQL (`DROP`, `TRUNCATE`, unscoped mass `DELETE`, or database recreation), unless the user explicitly authorizes that exact operation and confirms an isolated disposable database.
- Do not infer permission for destructive database operations from a general implementation request.
- `php artisan migrate`, additive migrations, `INSERT`, targeted `UPDATE`, targeted `DELETE`, and specifically requested seeders/imports are allowed when their scope is clear and the target connection has been verified.
- For automated tests, use the isolated test configuration from `phpunit.xml`; never override it to point to an operational database.
- Prefer read-only checks (`php artisan migrate:status`, `php artisan migrate --pretend`, linting, and view cache) when they are sufficient.
