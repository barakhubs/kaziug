# KaziUG

A job board for Uganda with a single-admin back office, written in plain PHP 8.2+ on PostgreSQL.
No web framework: a small front controller, router, DI container and middleware pipeline, with
server-rendered PHP templates and a little vanilla JavaScript for progressive enhancement.

**Public site:** job search (PostgreSQL full-text) with filters, sorting and pagination in the URL,
job pages with JSON-LD `JobPosting` and "how to apply" details, category pages, saved jobs (in the
browser), a double-opt-in newsletter, a Contact page and a paid CV help page (form + WhatsApp).

**Back office (`/admin`):** dashboard, jobs (status workflow, history, bulk actions, details page),
categories, newsletters (preview, test, send now or schedule, delivery tracking), subscribers (CSV
import/export), Settings with tabs (General: site details, logo, WhatsApp number, CV price,
maintenance mode · Social profiles · Email/SMTP), account and audit log. Lists are sortable,
searchable and paginated with a rows-per-page choice.

---

## Contents

1. [Local setup](#1-local-setup)
2. [Commands](#2-commands)
3. [Creating the admin](#3-creating-the-admin)
4. [Configuring SMTP](#4-configuring-smtp)
5. [Newsletter sending and the scheduler](#5-newsletter-sending-and-the-scheduler)
6. [Deploying to Laravel Cloud](#6-deploying-to-laravel-cloud)
7. [Architecture](#7-architecture)
8. [Where each SOLID principle is applied](#8-where-each-solid-principle-is-applied)
9. [Security](#9-security)
10. [Design decisions](#10-design-decisions)
11. [Testing](#11-testing)
12. [Extending](#12-extending)

---

## 1. Local setup

**Requirements:** PHP 8.2+ with `pdo_pgsql`, `sodium`, `dom`, `fileinfo` and `mbstring`; Composer;
Docker (for PostgreSQL 16 and Mailpit).

```bash
docker compose up -d                 # Postgres on port 5442 (databases kaziug + kaziug_test), Mailpit on 1025/8025
composer install
cp .env.example .env
php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"   # paste the output into APP_KEY in .env
php bin/console config:check         # extensions, env, database; expect "migrations pending" the first time
php bin/console migrate
php bin/console seed                 # optional: 14 sample jobs, 10 categories, placeholder social profiles
php bin/console admin:create         # prompts for email + password (see section 3)
php -S localhost:8000 -t public public/index.php
```

Open <http://localhost:8000> for the site and <http://localhost:8000/admin> for the back office.

> **Windows with Laravel Herd:** the `php` on your PATH may be *Herd Lite*, a static build without
> `pdo_pgsql`/`sodium`. Use the full Herd binary instead, e.g.
> `~/.config/herd/bin/php83/php.exe bin/console migrate`.

> **Why `public/index.php` after `-t public`?** It acts as the router script so URLs containing a
> dot (such as `/admin/subscribers/export.csv`) reach the app; real files in `public/` are still
> served directly.

### The full loop, locally

1. Sign in at `/admin` and go to **SMTP**: host `127.0.0.1`, port `1025`, encryption **None**,
   from email `jobs@kaziug.local`. Click **Send test email** and open Mailpit at <http://localhost:8025>.
2. In **Site settings**, fill in the **physical address** (required before any newsletter can go out).
3. **Jobs → New job**, fill the form, **Publish now**. It appears on `/`.
4. On the public site, subscribe in the footer, open the confirmation email in Mailpit and confirm.
5. **Newsletters → New newsletter**, save, preview, **Send test email**, then **Send now**.
6. Run `php bin/console newsletter:work` (or leave `php bin/console newsletter:work --loop` running).
   The newsletter page shows queued / sent / failed counts.

---

## 2. Commands

| Command | What it does |
|---|---|
| `php bin/console migrate` | Applies pending migrations, each in its own transaction (`--status` lists them) |
| `php bin/console seed` | Loads sample data. Never runs automatically; refuses in production without `--force`. `--only=jobs` or `--only=social` |
| `php bin/console admin:create` | Creates the single admin (refuses if one exists) |
| `php bin/console newsletter:work` | Sends one batch of queued newsletter emails. `--loop` runs a batch every minute; `--max-runs=N` stops after N |
| `php bin/console config:check` | Checks PHP version and extensions, required env vars, APP_KEY, database version, pending migrations and storage permissions. Exits non-zero on any failure |
| `vendor/bin/phpunit` | Runs the test suite (needs the `kaziug_test` database from Docker) |

---

## 3. Creating the admin

There is exactly one admin and **no** web route that creates users.

```bash
php bin/console admin:create
```

It prompts for an email and a password (at least 12 characters; on Unix-like terminals the
password isn't echoed). Running it again after an admin exists fails. That rule is also enforced by
the database: `admin_users` has a `singleton` column that must be `true` and is `UNIQUE`, so a second
row is impossible even if the code were bypassed.

To change the password later, use **Account** in the back office. Changing it signs out every
other session. **Sign out of all sessions** is on the same page.

---

## 4. Configuring SMTP

All email (subscription confirmations, newsletters, tests) goes through the SMTP server entered
under **Admin → SMTP**. No external email service is used.

| Field | Notes |
|---|---|
| Host / port | e.g. `smtp.yourprovider.com`, port `587` |
| Encryption | **STARTTLS** (port 587, most providers), **SSL/TLS** (port 465), or **None** (local Mailpit only; disables opportunistic TLS too) |
| Username / password | The password is encrypted before it is stored (section 9) and never shown again. Leave the field blank to keep it; tick *Remove the saved password* to clear it |
| From name / from email | Use an address on a domain whose SPF/DKIM records authorise this SMTP server, or mail will land in spam |
| Reply-to | Optional |

**Send test email** sends immediately and records the result ("OK" or the server's error), which
is shown on the page. The page header says whether mail is configured. Until it is, the dashboard
shows a warning and newsletters can't be sent.

`MAIL_TIMEOUT` (seconds) bounds every SMTP connection.

---

## 5. Newsletter sending and the scheduler

Web requests never send newsletters. **Send now** (or a schedule coming due) only inserts one
`newsletter_deliveries` row per active subscriber. The worker does the sending:

```bash
php bin/console newsletter:work          # one batch, then exit (cron style)
php bin/console newsletter:work --loop   # a batch every minute until stopped (long-running process)
```

Each run:

1. queues newsletters whose scheduled time has passed,
2. sends due deliveries, at most `NEWSLETTER_RATE_PER_MINUTE` within any rolling minute,
3. retries failures after **1, then 5 minutes**. After the **3rd** failed attempt the delivery is
   marked `failed` and listed under *Permanent failures* on the newsletter page,
4. never sends to someone who unsubscribed after the newsletter was queued,
5. marks a newsletter `sent` once nothing is queued (or `failed` if nothing at all went out).

Only one worker sends at a time: each run takes a PostgreSQL advisory lock, and claims deliveries
with `FOR UPDATE SKIP LOCKED` plus a lease. Running the worker on several machines is therefore safe.

**Local / VPS scheduling:** either keep `newsletter:work --loop` running (systemd, supervisor), or
add a cron entry:

```cron
* * * * * cd /path/to/kaziug && php bin/console newsletter:work >> storage/logs/worker.log 2>&1
```

---

## 6. Deploying to Laravel Cloud

Laravel Cloud runs PHP applications besides Laravel. The repository is ready for it: the web root
is `public/`, all configuration comes from environment variables, the app writes only to
`storage/` (logs), and everything that must survive a deploy (sessions, the logo) lives in Postgres.

1. **Create the application** from the Git repository and pick the **PHP 8.3 or 8.4** runtime
   (`pdo_pgsql` and `sodium` are among Cloud's bundled extensions). Check that the web/document
   root is `public/`, with `public/index.php` as the front controller. Cloud may detect the app as
   Symfony because of `bin/console`; that's fine, since both conventions use `public/`.
2. **Attach a database**: a Laravel Serverless Postgres database (PostgreSQL 15+). Cloud injects
   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. (If a platform only injects
   `DATABASE_URL`, that works too.)
3. **Environment variables** (Environment → Settings → Variables):

   | Variable | Value |
   |---|---|
   | `APP_ENV` | `production` (forces secure cookies and hides error details, whatever `APP_DEBUG` says) |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://your-domain` (used in email links) |
   | `APP_KEY` | `base64:…` generated once with the command in section 1. **Keep it stable**: changing it makes the stored SMTP password unreadable (re-enter it if that happens) |
   | `APP_TIMEZONE` | `Africa/Kampala` |
   | `DB_SSLMODE` | `require` |
   | `TRUSTED_PROXIES` | `*` (Cloud's load balancer sets `X-Forwarded-For` / `-Proto`) |
   | `NEWSLETTER_RATE_PER_MINUTE` | your SMTP provider's limit, e.g. `60` |
   | `SESSION_IDLE_MINUTES`, `MAIL_TIMEOUT`, `LOG_LEVEL` | optional, see `.env.example` |

4. **Build command:**
   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction
   ```
5. **Deploy command** (runs on each deployment, before traffic switches):
   ```bash
   php bin/console migrate && php bin/console config:check
   ```
   `config:check` fails the deploy if something is wrong (missing APP_KEY, non-https APP_URL, debug
   on, database unreachable or too old).
6. **Newsletter worker:** add a **custom background process** on the App cluster (or a worker
   cluster) with the command
   ```bash
   php bin/console newsletter:work --loop
   ```
   Cloud restarts it if it exits. Background processes run on **every replica**; that is safe
   (see section 5) but only one replica sends at a time. If the environment hibernates / scales to
   zero, keep the worker cluster awake so scheduled newsletters go out on time.
7. **First deploy:** open the environment's **Commands** tab and run `php bin/console admin:create`.
   If the command runner can't answer prompts, pipe the three answers (email, password, password)
   instead. The password will then sit in that command's history, so change it from **Account**
   right after signing in:
   ```bash
   printf '%s\n' 'you@example.com' 'a temporary passphrase' 'a temporary passphrase' | php bin/console admin:create
   ```
   Run `php bin/console seed --force` only if you want the sample data in production.
8. **Health check:** `GET /health` returns `200 {"status":"ok"}` when the database answers, `503` otherwise.

Logs go to `storage/logs/app-YYYY-MM-DD.log` as JSON lines (PHP-level errors to
`storage/logs/php-errors.log`). On Cloud, that disk is per replica and reset on each deploy, so
treat them as short-lived.

---

## 7. Architecture

```
bin/console                 CLI entry point (commands listed in config/commands.php)
config/                     app.php (env → config), bootstrap.php, container.php (all bindings),
                            routes.php (every route), commands.php, seeders.php
database/migrations/        0001…0015, plain SQL, one transaction each, idempotent
database/seeds/             sample_jobs.json (from the prototype)
public/index.php            front controller; public/assets/ holds CSS and JS
src/Core/                   framework pieces: Http (Kernel, Router, Route, Request, Response,
                            MiddlewarePipeline, Responder, HttpException), Container, Config, Env,
                            View/ViewScope, Csrf, CookieFactory, AppKey, ErrorHandler, Format,
                            Session (Session, stores), Database (PDO wrapper, Migrator, PgArray),
                            Log, helpers.php (the e() escaper)
src/Controllers/Public|Admin  thin controllers
src/Middleware/             SecurityHeaders, StartSession, CsrfCookie, ShareViewData, RenderErrors,
                            VerifyCsrfToken, MaintenanceMode, SharePublicData, IdleTimeout, Authenticate
src/Services/ (+Contracts)  business logic behind interfaces; Import/ (job sources, CSV parser)
src/Repositories/ (+Contracts)  all SQL, one repository per aggregate
src/Domain/                 entities, value objects and enums (JobStatus rules, ThrottlePolicy…)
src/Mail/                   MailerInterface, PHPMailer transport, composer, newsletter channel
src/Validation/             Validator, rule classes, form validators
src/Console/                ConsoleKernel, Io, Commands/, Seeders/
views/                      layouts/, partials/, public/, admin/, emails/, errors/
storage/logs/               the only directory the app writes to
tests/Unit, tests/Http      PHPUnit; Http tests drive the real kernel against kaziug_test
prototype/                  the original static front-end, kept for reference
```

**Request lifecycle:** `public/index.php` → `Kernel` → global middleware (security headers →
session → CSRF cookie → shared view data → error rendering → CSRF check → maintenance mode) →
`Router` → route middleware (`SharePublicData` for public pages; `IdleTimeout` + `Authenticate`
for `/admin`) → controller → service → repository.

### Routes

Public: `GET /`, `GET /jobs/{slug}`, `GET /categories/{slug}`, `GET /saved`, `GET|POST /contact`,
`GET|POST /cv-help`, `POST /subscribe`,
`GET|POST /subscribe/confirm/{token}`, `GET|POST /unsubscribe/{token}`, `GET /media/logo`, `GET /health`.

Admin (all behind auth except login): `/admin/login`, `/admin/logout`, `/admin` (dashboard),
`/admin/jobs…` (list, `/{id}` details, `/{id}/edit`), `/admin/categories…`, `/admin/newsletters…`,
`/admin/subscribers…`, `/admin/settings` (General), `/admin/settings/social…`,
`/admin/settings/smtp…`, `/admin/account…`,
`/admin/audit`. The full list is `config/routes.php`.

---

## 8. Where each SOLID principle is applied

| Principle | Where |
|---|---|
| **Single responsibility** | Controllers only validate input, call a service and return a response (e.g. `Controllers/Admin/JobsController`). Each concern is its own class: `Domain/JobStatus` holds the transition rules; `Services/Throttle` does rate limiting; `Services/HtmlSanitizer` sanitising; `Services/TokenService` tokens; `Services/FormTimer` spam timing; `Services/Import/CsvSubscriberParser` CSV parsing; `Services/NewsletterWorker` delivery batching and backoff; `Mail/EmailComposer` email layout and footer; `Mail/PhpMailerTransport` SMTP. `AuthService` checks credentials while `AdminGuard` manages the session. `SubscriptionService` (public opt-in) is separate from `SubscriberAdminService` (back office) |
| **Open/closed** | New behaviour is added by adding classes: newsletter delivery goes through `Mail/NewsletterChannelInterface` (bind a composite in `config/container.php` to add SMS etc.); job imports go through `Services/Import/JobSourceInterface` (`SampleJobSource` is one source); validation rules implement `Validation/RuleInterface` and are registered with `Validator::register()`; console commands and seeders are listed in `config/commands.php` / `config/seeders.php` without touching `ConsoleKernel` or `SeedCommand`; middleware are composed per route |
| **Liskov substitution** | Every `Pg*Repository` and its in-memory fake in `tests/Support/Fakes` satisfy the same interface; `PhpMailerTransportFactory` and `RecordingTransportFactory` are interchangeable; `PgSessionStore`/`ArraySessionStore` likewise |
| **Interface segregation** | Public job reads (`JobReadRepositoryInterface`) are separate from back-office writes (`JobWriteRepositoryInterface`), though one Postgres class implements both; `SettingsReaderInterface` (used on every page, never throws) is separate from `SettingsServiceInterface` (admin writes); `MailerInterface` has one method; `LockInterface` and `TransactionManagerInterface` are tiny on purpose |
| **Dependency inversion** | Controllers depend on `Services/Contracts/*Interface`; services depend on `Repositories/Contracts/*Interface`, `MailerInterface`, `LockInterface`, `TransactionManagerInterface`, never on PDO or PHPMailer. Every binding lives in one place, `config/container.php`, which is why tests can swap the mail transport and services can be unit-tested with fakes |

---

## 9. Security

- **Single admin, no sign-up:** see section 3. The database enforces it.
- **Passwords:** `password_hash` with argon2id (bcrypt if PHP lacks it). Rehashed on login when
  parameters change. Unknown emails are verified against a dummy hash so timing doesn't reveal them.
- **Login:** CSRF-protected; session ID regenerated and CSRF secret rotated on login;
  progressive lockout per IP and per email (5 free attempts per email, 20 per IP, then 1, 2, 4…
  minutes up to an hour); generic error messages; no open redirects after login.
- **Sessions:** stored in Postgres (`sessions` table, keyed by the SHA-256 of the cookie value, so a
  leaked table can't be used to hijack sessions). Cookies are `HttpOnly`, `SameSite=Lax`, and
  `Secure` in production or over HTTPS. Idle timeout: `SESSION_IDLE_MINUTES`. "Sign out
  everywhere" bumps a per-admin session version that every request checks.
- **CSRF:** every POST (forms and any AJAX via `X-CSRF-Token`) needs a token. It's a *signed double
  submit*: the browser holds a random secret in an HttpOnly cookie and forms carry
  HMAC(secret) under a key derived from `APP_KEY`. This needs no server state, so anonymous
  visitors and crawlers don't create session rows just to see the subscribe form. The one exemption
  is `POST /unsubscribe/{token}` (RFC 8058 one-click unsubscribe from mail clients, which send no
  cookies): the 256-bit token in the URL is the authorisation and the only effect is unsubscribing
  that address.
- **Output escaping:** every template prints through `e()` (`src/Core/helpers.php`). The only raw
  HTML printed is newsletter bodies, which come out of the sanitiser.
- **Security headers** (`Middleware/SecurityHeaders`): a strict `Content-Security-Policy` (no inline
  script or style anywhere; all JS and CSS are files), `X-Frame-Options: DENY`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `X-Content-Type-Options: nosniff`,
  `Permissions-Policy`, HSTS in production over HTTPS, `no-store` and `noindex` on admin pages.
- **SMTP password at rest:** libsodium `crypto_secretbox` (XSalsa20-Poly1305) with a key derived from
  `APP_KEY` by `sodium_crypto_kdf_derive_from_key` (a separate subkey per purpose: SMTP, CSRF,
  forms). Never displayed after saving (masked placeholder); never logged (the logger redacts
  secret-looking keys, and SMTP errors have the password stripped).
- **Newsletter HTML (stored XSS):** `Services/HtmlSanitizer` parses the HTML with DOMDocument and
  keeps only an allowlist: `p br strong em b i u h2 h3 ul ol li a blockquote hr`, with `href` on
  links restricted to `http(s):` and `mailto:`. Dangerous elements (`script`, `style`, `iframe`,
  `svg`, `form`…) are removed with their content; other unknown tags are unwrapped (text kept); every
  other attribute (`on*`, `style`, `class`…) and all comments are dropped. **Why this approach:** an
  allowlist on a parsed tree fails safe. Anything not explicitly permitted is removed, including
  vectors nobody has thought of yet, whereas regex or blocklist filters fail open. A full library
  such as HTML Purifier would need a new dependency, and the allowed vocabulary here is small enough
  to audit in one file. It runs on save **and** again when each email is built, and the admin
  preview is served in a sandboxed iframe with its own `default-src 'none'` CSP.
- **Uploads:** the logo is checked by its **bytes** (finfo + `getimagesizefromstring`): PNG, JPEG or
  WebP only, max 512 KB and 2000×2000 px. SVG is refused (it can carry script). It is stored in
  Postgres and served from `/media/logo` with its own restrictive CSP.
- **Subscriptions:** consent time and IP are stored; confirmation tokens are random 256-bit values
  and only their SHA-256 is stored (48-hour validity, single use). Confirm and unsubscribe links
  open a page with a button, and only the POST changes anything, so mail scanners that prefetch
  links can't confirm or unsubscribe anyone. The public form has a honeypot, a signed minimum fill
  time (3 s) and a per-IP rate limit (5 per hour, then progressive lockout). The response never
  reveals whether an address was already subscribed.
- **Audit log:** every admin action on jobs, categories, social profiles, settings, SMTP,
  subscribers, newsletters and the account is recorded with actor, action, entity, entity ID, IP
  and time. Privacy deletes keep only a masked address.
- **Errors:** production pages never show stack traces (debug is forced off when
  `APP_ENV=production`); details go to `storage/logs`.
- **SQL:** prepared statements everywhere; dynamic parts (sort columns, filters) are allow-listed.
- **CSV export:** cells starting with `= + - @` are prefixed with `'` (formula injection).

---

## 10. Design decisions

| Topic | Decision |
|---|---|
| Deleting a category that has jobs | **Blocked.** Soft-deleted jobs count too, because they still reference it. The message suggests deactivating instead. Nothing is reassigned silently. |
| Inactive categories | Hidden from filters and their page returns 404; their published jobs stay listed. |
| Job status flow | draft → published → closed → archived, plus *unpublish* (published → draft) and *reopen* (closed → published). Archived is final. Every change is recorded in `job_status_history`. `published_at` is set on the first publish and kept on re-publish. |
| Public visibility | Only `published`, not deleted, and not past `expires_at`. Defined once in `PgJobRepository::VISIBLE`. |
| Application deadline | Required on every job (`expires_at`). It is a calendar day: the job stays listed until 23:59:59 Kampala time on that day, then leaves the site. New jobs can't get a past deadline; when editing, an already-passed deadline may be kept. Shown on job cards ("Closes in 3 days"), as a highlighted box on job pages, in the admin list (sortable) and as `validThrough` in JSON-LD. Jobs created before the rule ask for a deadline on their next edit. |
| Salary | A job gives **either** a monthly amount (min/max) **or** a salary scale (e.g. `U4`, for government jobs), never both; both may be left empty ("Salary not disclosed"). Enforced by the form and a CHECK constraint. Scale-only jobs carry no amount, so they don't match the salary filters, sort after jobs with amounts, and have no `baseSalary` in JSON-LD (the scale is written into the description). |
| Number of positions | `positions`, default 1 (form limit 500). Shown on cards and job pages only when it's 2 or more ("3 positions"), always on the admin details page, and as `totalJobOpenings` in JSON-LD. |
| Application instructions | Optional plain text (`application_instructions`, up to 2,000 characters), e.g. documents to attach or where to deliver hard copies. It adds to the link/email, which are still required. Escaped on output with line breaks kept (never rendered as HTML), shown under "How to apply" on the job page and added to the JSON-LD description. |
| Sample jobs | `database/seeds/sample_jobs.json` can be edited freely (optional keys `salaryScale`, `posts`, `deadline`, `applicationInstruction`, `applicationUrl`, `applicationEmail`; an invalid email or URL is skipped). Tests seed from their own copy, `tests/fixtures/seeds/sample_jobs.json`, so they don't break when it changes. |
| Applying | Every job needs application details: a link (application page / more info), an email, or both. Jobs created before this rule (e.g. the seeded ones) ask for them on their next edit. |
| Legal pages | `/privacy`, `/terms`, `/cookies`. Wording in `config/content/legal/*.php` (plain text with `{site}`, `{url}`, `{contact}`, `{address}`, `{price}` filled from Settings). Written to match what the code does (Uganda Data Protection and Privacy Act, 2019); if you change what data the site collects or add analytics/ads, update the policies first. A test checks every cookie the site sets is listed in the Cookie Policy. Rate-limit records containing IP addresses are purged after 2 days, as the Privacy Policy states. Have the texts reviewed by a lawyer. |
| Contact and CV help forms | Messages are **emailed** to the contact email from Settings (Reply-To = the visitor) and **not stored**. No CV upload: visitors send their CV afterwards by email or WhatsApp. Same spam protection as the subscribe form (CSRF, honeypot, fill time, 5 per hour per IP). If the contact email or SMTP isn't set, the form is hidden and WhatsApp is offered. |
| WhatsApp | Number entered in international format under Settings → General; links use `https://wa.me/<digits>` with a pre-filled message. The CV price (default UGX 100,000) is set there too. |
| Saved jobs | Stay in `localStorage` (same key as the prototype); `/saved?ids=` renders whichever are still visible. |
| Subscriber CSV import | Imported rows become **active** only after the admin confirms that everyone in the file already consented (the import is audited). |
| Confirmation emails | Sent synchronously when someone subscribes (they're one-off and transactional, not newsletters); on SMTP failure the subscriber stays pending and can submit again. |
| Logo storage | In Postgres (`site_assets`), because Laravel Cloud's disk is reset on deploy. |
| Sessions | In Postgres, for the same reason and to support several replicas. |

---

## 11. Testing

```bash
docker compose up -d
vendor/bin/phpunit                  # everything
vendor/bin/phpunit --testsuite Unit # no database needed for most
```

`phpunit.xml` forces `APP_ENV=testing`, which points the app at `DB_TEST_DATABASE` (`kaziug_test`).
The base test class migrates it once and truncates it before every test, and **refuses to run
against a database whose name doesn't end in `_test`**. All email goes to an in-memory transport.

Coverage highlights:

- **Unit:** job status transitions (every pair), JobService and CategoryService with in-memory
  fakes, AuthService lockout, validators, search criteria parsing, tokens, form timer, CSV
  parsing/writing, the HTML sanitiser (16 attack cases), SecretBox, router, container, sessions,
  CSRF and the error handler.
- **HTTP (real kernel + Postgres):** a smoke test that requests every GET route as admin and as a
  visitor (and every admin POST without login); auth flows; jobs, categories, social profiles,
  settings and logo uploads, SMTP, subscribers, newsletters; the double opt-in flow; public
  search and filters; and the newsletter queue and worker (rate limit, backoff, permanent failures,
  scheduling, locking).

---

## 12. Extending

- **Another newsletter channel:** implement `Mail/NewsletterChannelInterface`, then bind a small
  composite channel that calls the email channel and yours in `config/container.php`.
- **Another job source** (feed, CSV): implement `Services/Import/JobSourceInterface` and pass it to
  `JobImporter::import()`, e.g. from a new console command.
- **A validation rule:** implement `Validation/RuleInterface` and call `Validator::register()`.
- **A console command:** implement `Console/CommandInterface` and add it to `config/commands.php`.
- **A schema change:** add `database/migrations/NNNN_name.sql` with a rollback note at the top; it
  runs inside a transaction and should be safe to re-run (`IF NOT EXISTS`, `CREATE OR REPLACE`).
