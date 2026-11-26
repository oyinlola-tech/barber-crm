# Implementation audit

Audit date: 2026-11-26

## Current stage

The repository is past scaffolding and has locally working core slices for a private single-shop CRM: account security, staff/RBAC, customer/consent/privacy, completed visits, WhatsApp webhook/inbox, deterministic follow-ups, templates, reviews, notifications, analytics, audit/health, and deployment configuration. It is not production-ready or feature-complete. The detailed status is in [feature traceability](feature-traceability.md).

## Repository and scope evidence

- The checkout is Git repository `oyinlola-tech/barber-crm` on `main`, with `origin/main` updated through the ten folder-grouped implementation commits. `.env`, generated caches, local session files, test databases and browser results are ignored. The pushed implementation commits are authored by the project owner and contain no co-author trailers.
- Laravel 13, PHP 8.4, React, Inertia, TypeScript and the locked Composer/pnpm dependencies are present. No new dependencies were added in the recent implementation slices.
- `pdo_pgsql` is not installed. `php artisan migrate:status` fails with “could not find driver”; the intended PostgreSQL migration state and business data were not accessed or changed.
- The active application exposes 74 non-vendor routes. Booking, appointment and availability behavior is absent from the route/controller/service layer. Historical appointment/availability migration definitions remain untouched pending a safe database review.
- No production credentials, live WhatsApp delivery, provider approval, email delivery, production deployment or production backup restoration are claimed.

## Implemented and locally tested

- Private owner provisioning, Fortify authentication, password recovery, email verification, TOTP MFA/recovery codes, privileged MFA enforcement and session revocation.
- Staff invitations, account suspension, role/permission management, delegation checks and security/audit recording.
- Business-scoped customer CRUD, normalized international phone numbers, notes/tags, active/archived filters with profile restore, customer import/export, anonymization safeguards, consent history, opt-out processing and stated return date.
- Completed visit recording/history and after-commit visit events.
- Visit corrections with revision-aware follow-up replacement, audited soft-void and restore workflows, plus business-timezone input conversion and UTC persistence.
- A responsive WhatsApp connection page with reusable React feature components; typed credentials DTO, centralized typed WhatsApp configuration, numeric ID validation, phone/WABA association verification before saving, safe provider errors and audited disconnect.
- Official WhatsApp Cloud API client structure; encrypted connection settings; signed, deduplicated webhooks; inbound text/status processing; shared inbox actions and manual replies constrained by consent and the 24-hour service window.
- Removed empty `.gitkeep` scaffolding and now-empty folders, including obsolete booking and tenant-isolation placeholders. Active Laravel middleware remain in `app/Http/Middleware`; no duplicate middleware layer was introduced.
- Deterministic visit follow-up scheduling, database queue/scheduler, idempotency, sending-hours, bounded retries, stalled-message recovery, consent revalidation and delivery uncertainty handling.
- Follow-ups stop for new inbound responses and after reviews according to template purpose. Opt-out, response and review cancellation leave sending/reconciliation records intact.
- Customer-note and tag CQRS commands independently check the actor's permission instead of depending only on the web Form Request. WhatsApp delivery callbacks now record `failed` after `sent`, while stale `sent`/`delivered` events cannot undo failure and stale failure events cannot regress delivered/read states.
- Marking a conversation as read uses an explicit internal write to the protected unread counter; a mobile browser flow now verifies assignment, internal notes, read state, close status, and consent-gated reply visibility.
- Follow-up schedules snapshot the latest inbound customer message ID. The worker rechecks that snapshot immediately before provider dispatch and cancels if a customer reply arrived during the claim window.
- Inactive-customer re-engagement rules use configured inactivity thresholds, approved retention/re-engagement templates, cycle and follow-up limits, customer consent, recent-response/frequency checks, and suppress messages while a customer-reported return date is today or later. Eligibility is checked again before delivery.
- Service catalog supports business-scoped listing, creation, editing, and reversible activation/deactivation. Mutations enforce permissions in the Form Request and CQRS command, record audit changes, and preserve inactive services out of the completed-visit service picker.
- Service categories support business-scoped listing, create/edit, ordering, assignment to services, audited deletion when unused, and prevention of deletion while assigned. Category permissions are centrally defined and migrated additively for installed manager roles.
- Staff can revoke pending invitations and update staff display names/phone numbers. The roles view includes a grouped canonical permission catalogue; an authenticated HTTP feature test now exercises custom-role creation, permission edits, invitation role updates, guarded deletion, and audit records. Staff deletion remains unavailable to preserve visit and audit attribution; suspension is the supported access-removal path.
- Local draft editing/preview and variable mapping, provider status display, review capture/list, visit notifications, date-filtered aggregate analytics, audit log and operational health views.
- Operational heartbeat verification uses Laravel's database queue: health is unhealthy while the worker probe is pending, then one queue-worker iteration processes it and records the worker heartbeat.
- Docker Compose/Nginx/PHP-FPM deployment configuration, loopback default web bind, readiness route, trusted proxy configuration and deployment notes.
- Deployment scripts retain the currently running PHP and Nginx images as `previous-stable`; `make rollback` switches app, web, queue and scheduler containers back without changing database state. The automatic recovery path passed a fake-Docker test that simulates a failed deployment and verifies restoration. It has not been exercised against a production deployment.
- Authenticated streaming encryption for PostgreSQL custom dumps, protected local artifact permissions, and a restore-to-new-database utility that refuses existing targets and leaves the active database unchanged.

## Verification results

- `vendor/bin/pest`: 123 passed, 831 assertions, 1 PostgreSQL-only integration test skipped by its environment guard (SQLite in-memory; includes Meta credential validation, secret omission, permissions, audited disconnect, custom-role HTTP lifecycle, direct CQRS permission checks, delivery-status ordering, follow-up response-race cancellation, database queue-worker heartbeat processing, single-business middleware boundaries, and service catalog and category permission/migration checks).
- `pnpm test:e2e`: 6 Playwright Chromium tests passed, including service/category creation, editing, assignment, guarded deletion and activation/deactivation in a 390px browser; customer create/archive/restore; inbox assignment/note/read/close actions; consent-gated reply visibility; and a read-only authenticated page sweep across staff, roles, automations, templates, reviews, analytics, notifications, audit, health, business settings and WhatsApp settings.
- `composer analyse`: PHPStan/Larastan level 5 passed.
- `vendor/bin/pint --test`, `pnpm types:check`, `pnpm lint`, `pnpm format:check`, `pnpm build`: passed.
- Vite page imports are lazy-loaded. Latest output includes a 44.68 kB app entry and a 356.81 kB shared React runtime chunk; no chunk exceeds the 500 kB warning threshold.
- `composer validate --no-check-publish`, `composer audit --locked`, and `docker compose config -q`: passed.
- Docker app, queue, scheduler and web images built; `php artisan --version`, PHP-FPM config test, and Nginx config test passed in disposable containers.
- A fresh disposable PostgreSQL 17 container applied all 20 migrations, including the service update and service-category permission migrations; `migrate:status` showed all ran. The container and its temporary network were removed.
- A separate PostgreSQL 17 integration test held a real row lock while two Artisan dispatchers ran, verified both skipped the locked row and queued distinct messages, then confirmed the row became claimable after release. It also verified stalled pre-send versus provider-send recovery states. It passed 1 test / 15 assertions; the disposable stack and volume were removed.
- The backup stream unit test passed 1 test / 3 assertions including ciphertext tamper rejection. A separate disposable PostgreSQL 17 drill created a known row, dumped and encrypted the database through the runtime image, restored it into a new database and verified the row; its stack and volume were removed. No remote copy or production restoration is claimed.
- `php artisan migrate:status` on the host could not run because its PHP runtime lacks the PostgreSQL PDO driver. The configured PostgreSQL database was not accessed.

## Incomplete or externally blocked

- The stated return date is stored as customer information and suppresses re-engagement sends while the date is current/future; it does not reserve a booking. No free-text WhatsApp parser infers a return date.
- The WhatsApp opt-out keyword list still uses static application configuration; owner-managed, persisted keywords in messaging settings remain incomplete.
- Staff profile deletion is intentionally unsupported; suspension preserves historical attribution.
- Broader browser interaction, full visual QA and accessibility review remain incomplete; Playwright exercises customer creation, consent, notes, tags, visit create/correct/void/restore and representative read-only routes at 390px, but does not cover every module's write actions or a full keyboard/screen-reader audit.
- The configured PostgreSQL migration/schema/data review, existing-dataset constraints and any corrective migration are unverified. The current host lacks `pdo_pgsql`, so `php artisan migrate:status` cannot inspect that database; no configured or production database was changed. Locking and concurrent dispatch were tested against an isolated PostgreSQL 17 database; production-database behavior and process restart recovery remain unverified. Historical appointment/availability migration definitions remain retained pending review of the actual database.
- Docker app, queue, scheduler and web image builds plus fresh-database migration, readiness, and heartbeat checks passed. Deployment/rollback shell control flow passed a fake-Docker test. Production HTTPS/reverse proxy deployment, actual host rollback, restart recovery, remote encrypted backup copy, retention and production restore are unverified.
- Meta credentials, business verification, webhook subscription, approved templates and live messaging; SMTP/API mail delivery; production Sentry; and legal/privacy compliance remain external setup and verification work.

No destructive database cleanup was performed. Before deployment, inspect the intended PostgreSQL database and migration table with an authorized backup, resolve retained historical schema safely, set production secrets, then perform controlled provider and operations verification.
