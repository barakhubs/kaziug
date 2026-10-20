# KaziUG: project guide for Claude Code

Full-stack job board for Uganda with a single-admin back office. Read this file before every task.

## What this project is
- Public site: job listings with search and filters, job pages, category pages, newsletter subscription (double opt-in), visible social profiles.
- Back office at /admin: the only user is the admin. Manages jobs, categories, social profiles, newsletters, subscribers, SMTP settings, site settings, the admin account and the audit log.
- There is no visitor or company sign-up, and no user accounts beyond the single admin.
- The original front-end prototype is in `prototype/` (reference only, do not copy it wholesale). Reuse its design and behaviour, and move data and logic to the server.

## Stack (fixed, do not change without asking)
- PHP 8.2+, vanilla, no web framework (no Laravel, Symfony framework, Slim, etc.).
- PostgreSQL 15+ through PDO (pgsql). Always use prepared statements.
- Composer only for autoloading and these libraries: phpmailer/phpmailer, vlucas/phpdotenv, phpunit/phpunit (dev). Ask before adding any other package.
- Hosting: Laravel Cloud. Web root is `public/`. All config comes from environment variables.
- Server-rendered PHP templates. Vanilla JS only, for progressive enhancement.
- Newsletters are sent by the app itself through the admin-configured SMTP. No external email service.

## Architecture
- Front controller: `public/index.php`. Everything else lives outside the web root.
- `src/Core`: router, request, response, session, CSRF, container, view renderer, DB connection, middleware pipeline.
- `src/Controllers`: thin. Validate input, call one service, return a response. No SQL, no business rules.
- `src/Services`: business logic (JobService, CategoryService, NewsletterService, SubscriberService, MailService, SettingsService, AuthService, AuditService).
- `src/Repositories`: all SQL, one repository per aggregate, each implementing an interface.
- `src/Validation`, `src/Middleware` (auth, CSRF, rate limit, security headers).
- `views/public`, `views/admin`, plus shared partials. Templates escape output through the shared helper. No logic beyond display.
- `database/migrations` (numbered, transactional SQL), `database/seeds`.
- `bin/console`: migrate, seed, admin:create, newsletter:work, config:check.
- `storage/logs`, `storage/uploads` (outside the web root). `tests/` for PHPUnit.

## SOLID rules
- Controllers depend on service interfaces. Services depend on repository interfaces. Never construct dependencies inside a class; use the container.
- One responsibility per class. If a class needs a "and" in its description, split it.
- Mail sending goes through `MailerInterface`. Never call PHPMailer from services or controllers directly.
- Add new behaviour by adding classes, not by editing existing senders or handlers, where practical.

## Security rules (non-negotiable)
- There must be no public registration, sign-up, or user-creation route. The admin is created only with `bin/console admin:create`, which refuses to create a second admin. A unique constraint enforces this in the database.
- Hash passwords with `password_hash` (argon2id if available). Never log passwords, tokens or SMTP credentials.
- CSRF token on every state-changing request, including AJAX.
- Session: regenerate the ID on login, Secure + HttpOnly + SameSite cookies, idle timeout.
- Rate-limit login (by IP and by email) with progressive lockout. Rate-limit the public subscribe form.
- Encrypt the SMTP password at rest with libsodium, using a key derived from `APP_KEY`. Never show it back after saving; show a masked placeholder.
- Sanitise admin-authored newsletter HTML with an allowlist. Never output raw HTML from the database except through that sanitiser.
- Subscription tokens: generate random values, store only a hash of confirmation tokens.
- Log every admin action to `audit_logs` (actor, action, entity, entity id, IP, time).
- Production error pages must not show stack traces.

## Database conventions
- Table and column names: snake_case, plural table names.
- Every migration is transactional and has a matching rollback note at the top.
- Soft-delete jobs with `deleted_at`. Other deletions are hard deletes unless the table has audit requirements.
- Status values are enforced with CHECK constraints. Job statuses: draft, published, closed, archived. Job status changes are recorded in `job_status_history`.
- Add an index for every foreign key and every column used in a WHERE or ORDER BY on a large table.
- Store timestamps as `timestamptz`.

## Public site rules
- Only published jobs that have not expired appear publicly.
- Search uses PostgreSQL full-text search over the tsvector column.
- Every newsletter email includes a working unsubscribe link and the physical address from site settings.
- Job pages include meta tags and JSON-LD JobPosting markup.

## Back office rules
- Status changes follow: draft → published → closed → archived. Archiving does not delete. Show the status history.
- Deleting a category that still has jobs must either be blocked or reassign those jobs. Follow the decision recorded in the README.
- Newsletter sending never runs inside the web request. Sends are queued as `newsletter_deliveries` rows and processed by `bin/console newsletter:work`, which is run by a scheduler once a minute. Respect the per-minute rate limit, and retry failed sends up to 3 times with backoff.
- Subscriber import and export are CSV only, validated row by row.

## Commands
```
docker compose up -d                  # local Postgres 16 on port 5442 (creates kaziug + kaziug_test)
composer install
cp .env.example .env                  # then fill in values
php bin/console config:check
php bin/console migrate
php bin/console seed                  # sample data, only when requested
php bin/console admin:create
php -S localhost:8000 -t public public/index.php   # local server (router script needed for paths with dots)
php bin/console newsletter:work       # run by the scheduler in production
vendor/bin/phpunit
```
If a command here does not exist yet, create it and update this section.

## Code style
- `declare(strict_types=1);` in every PHP file. PSR-12 formatting. PSR-4 namespaces under `App\`.
- Type every parameter, return value and property.
- Prefer small, named methods. No logic inside templates.
- Comments explain why, not what. No commented-out code.
- Use English for code, identifiers and UI text. Currency is UGX unless a field says otherwise.

## Workflow
- For any non-trivial task, first write a short plan and wait for approval.
- Build in stages. After each stage, confirm it works and write a short progress note: what changed, what is tested, what is open.
- Add or update tests for each service you change. Run `vendor/bin/phpunit` before reporting a task done.
- Do not add features outside the agreed scope. If a requirement is ambiguous, ask instead of guessing.
- Do not change the stack, the hosting target or the security rules without asking.
- Never commit `.env`, uploads, logs or credentials. Keep `.env.example` current.

## Things to avoid
- Adding a web framework, ORM or external email/marketing service.
- Adding any route that creates a user other than the admin.
- Building SQL strings with concatenated input.
- Putting business logic in controllers, views or migrations.
- Exposing SMTP passwords, tokens or stack traces in any response.
## Local environment notes
- On this machine the `php` on PATH is Herd Lite (static build, no pdo_pgsql/sodium). Use the full Herd binary: `~/.config/herd/bin/php83/php.exe` (and `~/.config/herd/bin/composer.phar` through it).
- Tests use the `kaziug_test` database (`DB_TEST_DATABASE`); phpunit.xml forces `APP_ENV=testing`, and the test base class refuses any database not ending in `_test`.
