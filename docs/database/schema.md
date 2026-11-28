# Database schema overview

PostgreSQL is the only required application database. Laravel migrations define the schema. Business-owned rows use `business_id` and relationships are scoped to the one active configured shop; those keys do not create SaaS workspace support.

| Group | Tables / records |
|---|---|
| Accounts and access | `users`, `businesses`, `business_members`, `staff_profiles`, `staff_invitations`, `roles` |
| Customers and consent | `customers`, `customer_consents`, `customer_notes`, `customer_tags`, tag assignments |
| Completed visits | `visits`, visit-service relationship records |
| Messaging | `whatsapp_connections`, `message_templates`, `conversations`, `conversation_notes`, `messages`, `message_events`, `webhook_events` |
| Automation | `automation_rules`, `scheduled_messages`, `automation_executions` |
| Feedback and accountability | `reviews`, `review_requests`, `audit_logs`, `notifications`, operational heartbeats |
| Laravel infrastructure | `sessions`, password reset tokens, `jobs`, job batches, failed jobs, cache and cache locks |

Customer consent records may include a controlled purpose and an optional evidence reference. New grants must identify the supported customer follow-up purpose; older records remain nullable and are displayed as legacy consent without an inferred purpose. Evidence references should point to the shop's own consent record and must not contain unnecessary sensitive data.

The precise columns, constraints, indexes and migration history are defined by `database/migrations`. Migration files that may already be applied must not be edited. The historical appointments/availability migration is retained pending inspection of the configured PostgreSQL migration table and data; there is no active appointment behavior. See [relationships](relationships.md) for the main data paths.

The business profile stores its description, address, HTTPS public-review URL and private logo path on the existing single-shop `businesses` record. Logo bytes live on Laravel's private local disk and are served only by an authenticated, permission-checked route; they are not exposed through the public storage symlink.

The current PHP 8.6 CLI has `pdo_pgsql`, but read-only migration-status inspection failed because the configured local PostgreSQL credentials were rejected. A fresh disposable PostgreSQL 17 database successfully applied all 29 migrations in this checkout, including the additive consent-purpose migration. This proves fresh-schema compatibility only; it does not establish the configured business database's migration state or data. Do not apply or remove schema changes in a business database without a verified backup and migration-status inspection.
