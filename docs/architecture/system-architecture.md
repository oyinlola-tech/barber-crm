# System architecture

This is a private single-barbershop CRM delivered as one Laravel application. Laravel serves React pages through Inertia, stores application data in PostgreSQL, and runs background work through Laravel's database queue and scheduler. Nginx and PHP-FPM are used for the container deployment. See the root [architecture](../../ARCHITECTURE.md) for the binding boundaries and security rules.

## Runtime paths

- Browser page: route → authentication and security middleware → Form Request → thin controller → CQRS query or command → repository/Eloquent → PostgreSQL → Inertia response.
- Outbound follow-up: scheduler → due-message dispatcher → database queue → worker → delivery command → consent recheck → official Meta WhatsApp Cloud API → provider status webhook.
- Inbound webhook: raw-body signature verification → deduplicated webhook record → queue job → shared CQRS operations → conversation/message update and staff notification.
- Operational work: scheduled heartbeat, stalled-message recovery, webhook payload retention, orphan delivery reconciliation, and due-message dispatch.

## Boundaries

One active barbershop is resolved for authenticated staff. Stored `business_id` keys preserve data relationships and enforce scope; they do not enable workspaces or tenant selection. Customer visits are completed-visit records, not reservations. WhatsApp requires separately configured Meta credentials and approvals. The application does not claim those external prerequisites are present.

## Reliability and data safety

Scheduled messages are durable database records, with transaction-backed claims, bounded retries, consent checks at send time, and provider delivery state separate from scheduling state. An ambiguous provider outcome is not blindly resent. Webhook payload retention redacts raw content while preserving deduplication evidence; minimal provider message identifiers let the system reconcile a delivery callback that arrived before the outbound message record. Rollback restores prior app/web images and never rolls back database migrations.

## Evidence and limits

The implementation audit and feature traceability report distinguish tested local behavior from incomplete and externally blocked behavior. A local codebase or isolated PostgreSQL test does not verify the configured or production database, Meta activation, mail, deployment, or production backup recovery.
