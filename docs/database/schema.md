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

The precise columns, constraints, indexes and migration history are defined by `database/migrations`. Migration files that may already be applied must not be edited. The historical appointments/availability migration is retained pending inspection of the configured PostgreSQL migration table and data; there is no active appointment behavior. See [relationships](relationships.md) for the main data paths.

The current host PHP environment lacks `pdo_pgsql`, so it cannot inspect the configured database. Disposable PostgreSQL migration tests do not prove the production schema state. Do not apply or remove schema changes in a business database without a reviewed backup and migration-status inspection.
