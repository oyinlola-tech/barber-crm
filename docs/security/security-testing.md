# Security testing and verification

## Scope

Security verification in this repository consists of automated tests against
the application and controlled local infrastructure. It does not constitute a
production penetration test, independent red-team assessment, legal review, or
verification of the deployed host.

## Run locally

Use the repository's normal test environment with isolated test data and no
live WhatsApp credentials or customer phone numbers:

```sh
php artisan test --compact
composer analyse -- --no-progress
vendor/bin/pint --test
pnpm types:check
pnpm lint
pnpm format:check
pnpm build
pnpm test:e2e
```

Run database-specific locking, migration, and constraint scenarios against a
disposable PostgreSQL instance. Never point tests or destructive verification
at a production database. WhatsApp requests must use Laravel HTTP fakes or
controlled test doubles; automated tests must never send real messages.

## Existing security coverage

The current suite contains focused checks for:

- Login/account MFA, recovery code consumption, signed email verification,
  session ownership and revocation, password hash configuration, privileged
  MFA enforcement, and suspension/session invalidation.
- Role delegation, reserved owner role protection, command-level permission
  enforcement, and representative unauthorized route and cross-business
  access cases.
- Customer consent and opt-out, customer export authorization and CSV formula
  handling, anonymization behavior, customer-data redaction, and protected
  in-flight message handling.
- WhatsApp raw-body signature validation, malformed and oversized payloads,
  deduplication, account identifiers, replay/ordering cases, and webhook
  payload retention/redaction.
- Secure response headers, CSP nonce behavior, HTTPS session configuration,
  explicit trusted-proxy configuration, and operational health reporting.

For evidence, see the corresponding tests under `tests/Feature/Auth`,
`tests/Feature/Staff`, `tests/Feature/Customers`,
`tests/Feature/Consent`, `tests/Feature/WhatsAppWebhookSecurityTest.php`,
`tests/Feature/WhatsAppWebhookRetentionTest.php`, and the security/middleware
tests. The implementation audit and feature traceability report record the
latest local run and its limitations.

## Review procedure for changes

For each security-sensitive change, identify the trust boundary and attacker-
controlled input; trace the route/controller through validation, CQRS use
case, repository/model, queue job or provider client as applicable. Verify
authorization and business scoping at the server-side use-case boundary, not
only in the UI. Add focused negative tests for missing permissions, cross-shop
identifiers, malformed input, duplicate/out-of-order events, and failure or
retry paths. Check logs and audit metadata for secrets and unnecessary customer
content. Run the targeted test and static/style checks, then the broader suite
for shared middleware, auth, queue, or database changes.

## Limits and deployment checks still required

- The host PHP runtime previously lacked `pdo_pgsql`, preventing inspection of
  the configured database migration state. Disposable PostgreSQL tests do not
  prove the state or integrity of the configured/production database.
- No production host, TLS edge, real trusted-proxy chain, deployed session
  cookie, production mail transport, live Meta webhook, or live WhatsApp send
  has been security-tested here.
- No external penetration test, dependency vulnerability response exercise,
  incident-response drill, or legal/privacy audit is claimed.
- Review production secrets, network exposure, file permissions, container
  privileges, database roles, backup access, log retention, and alerting during
  deployment. Keep PostgreSQL private and verify only the intended HTTPS
  ingress is exposed.

Record new findings and their regression tests in the implementation audit.
Do not describe a check as passed unless its exact command or controlled
scenario ran successfully in the target environment.
