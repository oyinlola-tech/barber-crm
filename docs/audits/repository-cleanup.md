# Repository audit and scope correction

Audit date: 2026-11-26

This report preserves the initial repository audit snapshot. The Git checkout and owner-configured remote were available later in implementation; see [implementation audit](implementation-audit.md) for current state.

## Repository state before correction

- At the time of the initial audit, no `.git` directory was visible, so Git status, diff, history, and migration provenance could not be established. Existing files were treated as potentially user-owned.
- Composer dependencies show Laravel 13.44.1, Fortify 1.44.0, Inertia Laravel 3.8.0, Pest 5.3.7, PHPUnit 13.4.7, Sentry Laravel 4.31, and libphonenumber-for-php 9.0.44. Larastan 3.15 is configured for PHPStan. The JavaScript manifest shows React 19, Inertia React 3, Vite 8, Tailwind 4, TypeScript 6, Prettier, and pnpm 11.
- PostgreSQL was configured in the local `.env`, but the PHP runtime lacked the PostgreSQL PDO driver. `php artisan migrate:status` could not connect, so no claim is made about which migrations are applied or whether a production database contains records.
- The initial test baseline was 12 passing tests. The current local suite passes 91 tests / 551 assertions using SQLite in memory.
- The repository already contained a Laravel/Inertia scaffold, Fortify login plus public registration, business membership and session-based workspace selection, customer/consent/visit models, early WhatsApp and automation code, appointments, staff availability, reviews, and dashboard scaffolding.

## Removed or corrected

- Removed appointment routes, controllers, services, repository contracts/implementations, policy, status enum, validation request, test, and frontend placeholder directories.
- Removed staff working-hour and availability-exception application code and routes.
- Removed public Fortify registration, its registration view, and the login-page workspace registration link.
- Replaced session-selected tenant/workspace resolution with a single-shop resolver that selects exactly one active business and checks the signed-in user's active membership. The old `tenancy.php` config was removed and routes now use `business` middleware.
- Removed appointment-trigger values from application automation triggers and removed appointment relation loading from customer reads.
- Removed appointment permissions from seeded role defaults and updated dashboard analytics to count actual CRM records rather than appointment/revenue metrics.
- Replaced the starter dashboard text with data-driven customer retention, consent, visits, follow-up, delivery, review, and connection statistics.
- Added an interactive `app:provision-owner` command that creates the first owner and barbershop using the existing CQRS account command. It refuses to run after any user or barbershop exists and accepts passwords through a hidden prompt only.
- Added configurable phone-number validation and E.164 normalization, defaulting to Nigeria while accepting valid international numbers.
- Added PHPStan/Larastan, Sentry with PII collection disabled and tracing off by default, Prettier, and a GitHub Actions CI workflow.
- Added staff invitations and acceptance, staff suspension with session revocation, constrained role delegation, custom roles, customer private notes/tags/CSV import/export, anonymization safeguards, consent, and visit interfaces. Added Fortify TOTP MFA/recovery codes, email verification and session management; inbox/webhook processing; deterministic follow-up stop rules; template draft editing/mapping; reviews, notifications, analytics, audit and operational health interfaces; and deployment guidance. See `feature-traceability.md` for limits.
- Replaced stale architecture and setup text with the corrected single-barbershop scope and explicit implementation boundaries.

## Retained

- Dependencies were retained where their purpose was not exclusive to removed functionality. Composer and pnpm lockfiles were updated for phone parsing, Sentry, PHPStan/Larastan, and Prettier; Symfony Serializer was moved to a patched locked release.
- Existing `businesses` and `business_members` tables/models remain as the single barbershop profile and authorized staff membership store, not a SaaS tenancy or workspace-switching feature.
- `business_id` record scoping remains to protect the one barbershop's data and preserve foreign-key ownership.
- Service descriptions associated with completed visits remain in the current data model; they are not appointment booking, POS or inventory behavior.
- Historical migration files were not edited or rolled back.

## Legacy database structures deferred

The existing migrations still create `appointments`, `appointment_services`, `availability_exceptions`, `staff_working_hours`, an optional `visits.appointment_id`, and `visit_services`. They came from the prior scaffold and are out of the active product scope. They are retained in migration history because the PostgreSQL driver was unavailable and the applied migration state and any existing data could not be inspected. No destructive migration was run. Before removing or archiving those structures, inspect migration status against the intended database, make and verify a backup, check table contents and dependent foreign keys, then prepare a reviewed forward migration that preserves any required records.

## Remaining incomplete work

This repository is not yet a complete CRM or production-ready system. Inactive-customer re-engagement with stated-return-date suppression and staff/invitation lifecycle controls are now implemented and locally tested. Browser QA, PostgreSQL schema and migration review, production deployment and backup restoration remain incomplete or unverified. WhatsApp credentials, approved templates, Meta business verification, live activation and real email delivery were not verified.

## Verification and limits

- `php artisan route:list --except-vendor` lists 74 non-vendor routes; no booking, appointment, availability, payment or subscription endpoints appeared.
- `php artisan test --compact` passed 93 tests (558 assertions), including visit correction, void/restore and business-timezone handling alongside customer privacy, automation, consent, inbox, templates, analytics, security and webhook behavior.
- `composer analyse` passed at PHPStan level 5.
- Pint, ESLint, TypeScript, Prettier, and the Vite production build passed.
- `composer validate --no-check-publish`, `composer audit --locked`, and Docker Compose configuration validation passed. After switching Inertia pages to lazy imports, the app entry is 44.68 kB and the largest shared runtime chunk is 356.81 kB; Vite reports no chunk above its 500 kB warning threshold.
- Host `php artisan migrate:status` failed because `pdo_pgsql` is missing. A fresh isolated PostgreSQL 17 container later applied all 20 migrations. The configured database and existing data were not accessed. Production deployment, concurrency against the configured DB, live providers, backups and restoration remain unverified. Docker app, queue, scheduler and web images build; isolated PHP-FPM and Nginx checks pass.
