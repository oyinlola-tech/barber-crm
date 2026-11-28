# Repository instructions

## Product boundary

This repository is a private CRM for one existing barbershop. Keep one Laravel application with React and Inertia. The supported product covers staff access, customers, consent, completed visits, official WhatsApp messaging and inbox, deterministic follow-up automation, templates, reviews, reporting, settings, notifications, audit records, and operations.

Do not add AI or agents, appointment scheduling or time-slot bookings, availability calendars, payment/POS/inventory/payroll, subscriptions, SaaS onboarding or workspace switching, multi-business tenancy, mobile apps, separate frontend/backend applications, Redis, Horizon, WebSockets, microservices, Kubernetes, event sourcing, vector search, or public developer APIs. A customer may share a preferred return date through WhatsApp; store it only as a non-reserving visit intention that notifies staff, never as a slot, appointment, or paid booking. Staff may mark the date arrived or missed; arrived creates a completed visit and missed can trigger an enabled, consent-checked WhatsApp rule.

## Stack and conventions

Use the versions locked by Composer and pnpm: Laravel 13.44.1, PHP 8.6, PostgreSQL, Eloquent, React, TypeScript, Inertia 3, Vite, Tailwind, Fortify, database queue/cache/session, and Meta WhatsApp Cloud API. Confirm a package's installed version before relying on version-specific APIs. Do not change dependencies without authorization.

Follow Laravel conventions and the centralized Ansofra CQRS layout:

- Put mutations in `app/Services/<Domain>/Commands` and reads in `Queries`.
- Each use case exposes an `execute()` method; do not add a command bus or duplicate handlers.
- Keep controllers focused on HTTP input/output. Use Form Requests, policies, DTOs, repositories and Eloquent for their defined responsibilities.
- Use Laravel's service container, middleware, events, notifications, queues, scheduler and migrations.
- Scope access to the single configured barbershop. A request's business identifier is never authorization.
- Dispatch dependent work after commit, use transactions for critical writes, make jobs idempotent and bound retries.

## Security and privacy

There is no public registration. Provision the first owner with `php artisan app:provision-owner`; credentials must never be hardcoded or passed as command-line password arguments. Protect owner access and require server-side authorization. Recheck consent immediately before WhatsApp delivery. Never log access tokens, passwords, recovery codes or unnecessary customer content. Validate webhook signatures against the raw body and persist deduplicated events before background processing.

Do not claim provider activation, external compliance, live delivery, backup restoration or production readiness without direct evidence. Do not send real WhatsApp messages in automated tests.

## Data safety

Inspect migration status and existing data before schema changes. Never edit an applied migration or use destructive reset/clean commands. Use additive corrective migrations. Any operation that could destroy business data requires an explicit backup and review. The old appointment and availability migration definitions are retained pending PostgreSQL inspection; do not reintroduce their application routes or behavior.

## Quality

Use explicit parameter and return types, curly braces, descriptive names and typed frontend properties. Avoid TypeScript `any`, placeholder buttons, mock production data, unused abstractions, empty feature scaffolding and unapproved dependencies. Add focused tests for changed behavior. Run Pint for PHP changes, Pest for backend behavior, and the relevant TypeScript/lint/build checks for frontend changes.

Keep `ARCHITECTURE.md` authoritative. Audit and feature-status documents must distinguish working behavior from partial, externally blocked and unimplemented work.
