# Changelog

## 2026-11-27

- Default the application database, queue batches, and failed jobs to PostgreSQL when `DB_CONNECTION` is omitted.
- Reject wildcard trusted-proxy configuration so untrusted callers cannot supply forwarded IP or scheme headers.
- Keep audit-log CSV exports aligned with the action filter currently applied to the displayed records.
- Enforce actor permissions in customer CQRS mutations and exports; derive note access from the user and reject protected customer update fields.
- Enforce business settings and WhatsApp connect/disconnect permissions inside CQRS commands, rejecting protected business profile attributes.
- Reserve the `owner` role slug against custom role creation and renaming to prevent privilege escalation through the ownership permission shortcut.
- Record pre-send WhatsApp connection failures as definite failures instead of leaving manual inbox replies pending, and show outcome-specific feedback.
- Sanitize scheduled-message retry backoff configuration and clear stale failure timestamps before safe requeue.
- Show accessible save confirmation for business and messaging settings, and align analytics copy with the returning-customer calculation.
- Enforce staff authorization inside visit and consent CQRS commands, including direct non-HTTP invocation, and add regression coverage.
- Keep conversation activity timestamps monotonic when delayed inbound WhatsApp webhooks arrive; messages and unread counts are still recorded.
- Report WhatsApp integration health as degraded when the saved token cannot be decrypted, required IDs are missing, or the Graph API version is invalid.
- Centralized privileged MFA permissions in typed authorization config and extended the gate to staff-removal access.
- Force secure session cookies in production, add a regression test, and remove the insecure cookie override from the environment template.
- Recycle the database queue worker hourly under Docker's restart policy.
- Fix customer profile updates for staff who can edit customer details but cannot edit private notes; add mobile browser regression coverage.

## 2026-11-26

- Set Argon2id as the default password-hashing driver with centralized, configurable cost parameters and a hash-algorithm regression test.
- Enforced case-insensitive uniqueness for staff account email addresses in validation, commands, and an additive database index with a duplicate-data preflight; operational health now fails closed when PostgreSQL is unavailable.
- Removed blanket inline-script allowance from the Content Security Policy and issue a per-response nonce for Vite asset tags; middleware tests verify nonce rotation and policy matching.
- Closed an MFA exemption that allowed privileged session revocation before confirmed enrollment; a regression test verifies the request is blocked.
- Made deployment verify the queue worker and scheduler and automatically restore the previous stable release when web or worker health checks fail; recovery is rechecked and deploy still exits nonzero.
- Initialized customer consent controls from the latest saved record so an opt-out cannot appear selected after reopening the profile; added a reload regression flow.
- Added webhook and inbox coverage for safely displaying non-text messages without retaining provider media identifiers or downloading files.
- Added permission-checked approved WhatsApp template replies from the shared inbox, with centralized variable mapping, consent and eligibility rechecks, idempotent request keys, uncertain-send blocking, and stale-dispatch reconciliation. Added backend and mobile browser coverage; live Meta delivery remains unverified.
- Hardened `make rollback` to restart and verify the queue worker and scheduler as well as web health before reporting success; fake-Docker checks cover worker and startup failures.
- Added a scheduled-message inbox with status filtering, safe cancellation, permission-checked manual retry for definitive failures, bounded retries, audited CQRS actions, and a responsive page. Uncertain provider delivery cannot be retried.
- Added permission-checked review editing and review/audit CSV exports. Exports stream only business-scoped fields, neutralize spreadsheet formulas, use private no-store headers, and record export audit events; review edits avoid copying customer feedback into audit metadata.
- Added owner-authorized staff removal with session and password-reset revocation, personal profile redaction, owner/self safeguards, preserved visit/audit attribution, and a responsive browser workflow.
- Added safe deletion for unused local WhatsApp template drafts, with permission checks, audit records, and protection for provider templates and automation/scheduled-message references.
- Added owner-managed additional WhatsApp opt-out phrases, with standard withdrawal keywords always retained; covered backend authorization, audit, inbound processing, MFA-protected mobile editing, and persistence after reload. Latest local verification: 130 Pest tests / 877 assertions passed, one PostgreSQL-only host test skipped, and 9 Playwright tests passed.
- Added the missing WhatsApp settings page, extracted reusable connection status and credential form components, and verified Meta phone/WABA access and phone association before saving encrypted credentials.
- Added a typed WhatsApp configuration accessor and structured customer/WhatsApp command DTOs; made provider failures safe for the UI and audited privileged disconnections.
- Removed empty placeholder folders and `.gitkeep` files, including obsolete booking and tenant-isolation scaffolds; retained Laravel's active `app/Http/Middleware` pipeline.
- Corrected ignore rules so `.gitignore` and `.env.example` can be versioned while local `.env`, logs, sessions, test databases, browser results and analyzer caches stay excluded.
- WhatsApp connection feature coverage now passes 8 tests; the full Pest suite passes 103 tests / 672 assertions with 1 host-only PostgreSQL test skipped. PHPStan, Pint, TypeScript, ESLint, Prettier, Vite, deployment-script checks and the 4-test Playwright suite pass.
- Corrected the repository scope to one private barbershop CRM.
- Removed appointment and staff-availability application code and routes while retaining historical migrations pending a PostgreSQL data audit.
- Disabled public Fortify registration and added an interactive first-owner provisioning command.
- Replaced session-based workspace selection with a fixed single-shop resolver.
- Replaced appointment/revenue dashboard metrics with database-backed customer, consent, visit, messaging, follow-up, review, and connection counts.
- Added customer list and profile interfaces for customer records, consent, completed visits, and visit history.
- Rewrote project scope, architecture, setup, and audit documentation.
- Added configurable international phone validation and E.164 normalization, PHPStan/Larastan, privacy-conscious Sentry defaults, Prettier, and CI checks.
- Added Fortify TOTP MFA, recovery codes, email verification and session revocation; secured owner/staff access and invitations.
- Added customer import/anonymization safeguards, customer-reported return dates, follow-up response/review stopping rules, and template draft editing/mapping/preview.
- Added notifications, date-filtered aggregate analytics, audit and operations health interfaces.
- Hardened Compose deployment defaults, readiness checks and trusted proxy configuration; documented production and backup verification gaps.
- Added configurable inactive-customer re-engagement with consent, recent-reply and frequency safeguards, return-date suppression, repeat-cycle idempotency, delivery-time revalidation, and rule editing.
- Added staff invitation revocation, audited staff profile edits and grouped permission catalogue.
- Expanded Playwright to cover private sign-in, authenticated dashboard access, and customer creation at a 390px viewport using an isolated SQLite fixture.
- Changed Inertia page loading to lazy imports; the app entry chunk is now 44.68 kB and the largest React runtime chunk is 356.81 kB (no >500 kB chunk warning).
- Fixed the Docker frontend stage to include `pnpm-workspace.yaml`; app, queue, scheduler and web images now build successfully. PHP-FPM and Nginx configuration checks passed in disposable containers.
- Verified a fresh isolated PostgreSQL database: all 17 migrations applied, `/ready` returned HTTP 200, and scheduler/queue-worker heartbeats were recorded. The temporary test stack and its volumes were removed afterward.
- Added permission-checked visit corrections, audited soft void/restore, pending follow-up cancellation and revision-aware re-scheduling. Visit form values now use the barbershop timezone and persist in UTC.
- Added browser coverage for the visit record, correct, void and restore workflow on mobile.
- Matched Meta's webhook callback success response (`200 OK`), extracted deterministic button/list reply text for opt-out handling, and require the configured WhatsApp Business Account ID to match webhook events.
- Expanded Playwright with a mobile-width read-only smoke sweep across staff, roles, inbox, automations, templates, reviews, analytics, notifications, audit, health and settings. Browser tests now run serially against their shared isolated database and fixture account.
- Extracted the atomic due-message claim transaction into the scheduled-message repository and added an isolated PostgreSQL 17 integration test that starts two actual Artisan dispatchers concurrently, verifies distinct queued messages, and exercises stalled-message recovery. Added a PostgreSQL concurrency job to GitHub Actions.
- Added authenticated streaming encryption for PostgreSQL custom dumps, protected local backup files, a restore-to-new-database command, and an isolated PostgreSQL 17 dump/encrypt/restore/query drill. Remote copy and production recovery remain unverified.
- Added deployment image aliases and a no-argument `make rollback` command. `make deploy` retains the running app and web images as the previous stable release before replacing them; rollback recreates application services without changing PostgreSQL or migration state.
- Added a fake-Docker CI check for deploy image capture, no-argument rollback, and automatic recovery after a failed health check. Expanded the mobile customer browser flow to record consent, add a private note, and save tags.
- Added active/archived customer filtering and a profile restore action. Archived records no longer show repeat-archive, note/tag editing, or visit recording actions; archive/restore commands reject invalid status transitions.
- Current verification: 95 Pest tests / 608 assertions passed; one PostgreSQL-only test skipped under the host's SQLite-only environment. The PostgreSQL concurrency integration test passed separately (1 test / 15 assertions). The encrypted backup unit test passed (1 test / 3 assertions), and an isolated PostgreSQL 17 backup/restore round trip passed. Playwright passed 4 tests. PHPStan, Pint, TypeScript, ESLint, Prettier, Vite, Docker image builds, shell checks, and Compose configuration checks passed. The configured database, live providers, production deployment/rollback, and remote backup copy remain unverified.
