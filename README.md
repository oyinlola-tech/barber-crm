# Barbershop WhatsApp CRM

A private, single-barbershop CRM for customer records, completed visits, consent-aware WhatsApp follow-ups, conversations, and customer feedback. It uses one Laravel application with React and Inertia. See [ARCHITECTURE.md](ARCHITECTURE.md) for boundaries and the current implementation status in [the audit](docs/audits/repository-cleanup.md).

For module-by-module implementation limits and current verification results, see the [feature traceability](docs/audits/feature-traceability.md) and [implementation audit](docs/audits/implementation-audit.md).

## Requirements

PHP 8.6 with `mbstring`, `intl`, and `pdo_pgsql`, Composer, Node.js compatible with the locked Vite toolchain, pnpm 11, PostgreSQL, and Docker Compose (or equivalent local services). Laravel 13 is locked at v13.44.1.

## Local setup

```sh
composer install
pnpm install --frozen-lockfile
cp .env.example .env
php artisan key:generate
```

Set PostgreSQL connection values in `.env`, then create the database and run `php artisan migrate --no-interaction`. Do not use `migrate:fresh` against an existing database. Configure a mail transport before using password recovery or staff invitations.

Session cookies default to secure in production and cannot be downgraded by `SESSION_SECURE_COOKIE=false`; local HTTP development remains supported. Keep production behind HTTPS.

New account passwords use Argon2id (`HASH_DRIVER=argon2id`) with configurable memory, time, and thread costs. The example production defaults use 64 MiB of memory and four iterations; tune them only after checking the target server’s capacity.

For UI development, keep `php artisan serve` and `pnpm dev` running in separate terminals. For a built-asset check, run `pnpm build` and then start `php artisan serve`. If the page is blank, check the browser console and confirm the Vite server at `http://localhost:5173` is running; Laravel uses the `public/hot` marker to select Vite development assets. After stopping Vite, remove a stale `public/hot` marker before testing the built manifest again. The E2E test environment uses a separate hot-file path so a developer's local Vite process cannot make browser tests load dead asset URLs.

## Owner and staff access

There is no public registration. Provision the first shop owner interactively:

```sh
php artisan app:provision-owner
```

The command requests the password using a hidden terminal prompt. It exits without changes if any user or business already exists. The owner can invite staff through the dashboard; invitations require a private, working mail transport and use expiring single-use links. Configure and verify that transport before relying on invitation delivery.

## Queue and scheduler

Run `php artisan queue:work --tries=5 --timeout=60` as a supervised worker. Run `php artisan schedule:work` locally; production should invoke `php artisan schedule:run` every minute. Monitor failed jobs using Laravel's `queue:failed` commands. WhatsApp automation requires the worker and scheduler to stay online.

Staff notifications are available in-app and by email. Optional browser push can be enabled per device after configuring VAPID keys and HTTPS; see [browser push setup](docs/operations/browser-notifications.md). Generate a key pair with `php artisan app:web-push-vapid-keys`, then put both values in the deployment secret store. Push is queued and uses generic previews that do not expose customer details. SMTP delivery and browser push remain inactive until the shop supplies real mail credentials and VAPID keys.

## WhatsApp setup status

Configure the Graph API version, app secret, webhook verification token, and webhook URL through secret-managed environment settings. Configure the WhatsApp Business phone-number ID, business account ID, and access token in the protected integration settings. Follow the [WhatsApp connection guide](docs/integrations/whatsapp-setup.md) and [webhook setup guide](docs/integrations/webhook-setup.md). Meta business verification, template approval, webhook subscription, and live delivery are external dependencies and have not been verified in this repository.

## Verification

```sh
php artisan test --compact
CRM_POSTGRES_INTEGRATION=1 DB_CONNECTION=pgsql DB_DATABASE=barber_crm_test_dispatch php artisan test tests/Integration/Queue/PostgresMessageDispatchTest.php
vendor/bin/pint --test
composer analyse
pnpm lint
pnpm types:check
pnpm format:check
pnpm build
pnpm test:e2e
```

Automated tests use fakes and do not contact customer phone numbers. Playwright uses a dedicated disposable SQLite database at `storage/framework/playwright.sqlite` and checks private sign-in, customer and visit actions, and authorized page rendering at mobile width. The PostgreSQL integration test requires `pdo_pgsql`, the explicit `CRM_POSTGRES_INTEGRATION=1` flag, and a disposable database named exactly `barber_crm_test_dispatch`; it runs two dispatcher processes while a row lock is held and checks stalled-message recovery. Never set this flag for a business or production database. The integration test passed against a disposable PostgreSQL 17 container; the configured application database has not been inspected.

## Deployment

The Compose topology contains the application, Nginx, PostgreSQL, queue worker, and scheduler. Use `make deploy` to retain the last successfully deployed app/web image pair as the previous stable release. Use `make rollback` to automatically restore that pair without changing the database. Review [deployment and rollback notes](docs/operations/deployment.md) before production use. Configure HTTPS, restricted database networking, protected `.env` secrets, offsite encrypted backups, restoration drills, monitoring scrubbing, and Cloudflare DNS/edge protection. The encrypted backup helper and a restore into a new database were exercised against a disposable PostgreSQL 17 service; remote copy and production deployment have not been verified. See [`docs/operations/backup-and-restore.md`](docs/operations/backup-and-restore.md).
