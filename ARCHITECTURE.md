# Barbershop WhatsApp CRM architecture

This repository contains one private CRM installation for one existing barbershop. It is not a SaaS product. Business settings describe that shop; `business_id` columns and the existing membership relation are retained for referential integrity and staff ownership. The application resolves one active shop and provides no workspace switching or tenant onboarding.

## Approved scope

The application manages staff access, customers, messaging consent, completed visits, WhatsApp conversations, deterministic follow-up automation, templates, reviews, reporting, settings, notifications, audit records, and operational health. Customers do not need CRM accounts. WhatsApp delivery requires external Meta credentials, business verification, approved templates, and a publicly reachable webhook.

AI, agents, appointments, bookings, availability, payments, inventory, payroll, subscriptions, SaaS onboarding, multiple business workspaces, mobile apps, microservices, Redis, WebSockets, event sourcing, and public developer APIs are out of scope.

## Stack

- Laravel 13 / PHP 8.4, Eloquent and Form Requests
- Centralized Ansofra CQRS services with `execute()` use cases
- React 19, TypeScript, Inertia 3, Vite and Tailwind CSS 4
- PostgreSQL, database queue, database cache and database sessions
- Fortify for authentication, libphonenumber-for-php for E.164 validation, Sentry for optional error reporting, and the official Meta WhatsApp Cloud API
- Docker Compose for local and VPS deployment

Package versions are recorded in `composer.lock` and `pnpm-lock.yaml`.

## Request and job flows

Browser requests pass through Laravel routes, authentication and business access middleware, controllers, Form Requests, CQRS commands or queries, repositories, Eloquent, and PostgreSQL. Controllers handle HTTP input and responses only. Commands perform writes; queries are read-only. Repositories own persistence. Jobs and webhooks call the same use cases and must recheck consent and eligibility before sending.

Meta webhook requests are signature checked against the raw body, persisted with a provider event identifier, and queued for processing. The scheduler dispatches due records to the database queue. Provider acceptance is distinct from delivery; ambiguous provider outcomes must not trigger blind retries.

## Single barbershop boundary

The `business` middleware resolves the sole active business configuration and verifies the authenticated user's active staff membership. A second active business causes a service-unavailable response rather than workspace selection. Client-supplied business identifiers do not grant access. All record access remains scoped to the resolved business.

## CQRS areas

`app/Services` is the centralized use-case root. Each business area separates `Commands` and `Queries`. The codebase currently contains initial implementations for customer records, consent, completed visits, automations, WhatsApp connection setup, reviews, conversations, and dashboard statistics. See `docs/audits/feature-traceability.md` for evidence and gaps; this architecture description does not imply that every requested workflow is complete.

## Data and legacy schema

Migrations are append-only once applied. Existing migration files still create appointment, appointment-service, staff-hours, availability-exception, and visit-service structures from the earlier scaffold. They have not been dropped or rewritten because PostgreSQL is unavailable in this environment and migration history/data could not be inspected. The active application routes and business logic must not use appointment or availability concepts. A future reviewed migration may archive or remove those tables after a backup and PostgreSQL migration-status check.

## Security rules

- No public account registration; provision the first owner with `php artisan app:provision-owner`.
- Laravel's `app/Http/Middleware` pipeline owns trusted-proxy handling, browser security headers, verified-email enforcement, privileged MFA and single-shop resolution. Privileged permissions are centrally defined in `config/authorization.php` and accessed through `App\Configs\AuthorizationConfig`.
- Session cookies are forced secure when `APP_ENV=production`; local HTTP development remains supported. Keep production behind HTTPS and configure only the actual trusted proxy addresses.
- Never place passwords, provider tokens or recovery codes in source, command arguments, frontend props, audit metadata or logs.
- Enforce permissions server-side and scope records to the configured shop.
- Record and recheck WhatsApp consent both at scheduling and at send time.
- Use transactions for critical changes, `afterCommit()` for dependent work and idempotency keys for retries.
- Keep secrets out of logs and scrub customer data from monitoring payloads.

## Operating topology

One Laravel codebase serves Inertia pages and provider webhooks. Nginx and PHP-FPM serve HTTP traffic; PostgreSQL stores application, queue, session and cache data. Separate queue-worker and scheduler processes use the same application image. Redis, a separate API server, Kubernetes, and a Node.js production server are not required.
