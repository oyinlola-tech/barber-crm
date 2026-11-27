# Product scope

## In scope

One private CRM installation for a barbershop. Authorized staff manage customers, consent, completed visits, WhatsApp conversations, deterministic follow-up messages, templates, feedback, reporting, business settings, security, and audit records. PostgreSQL and Laravel's database-backed queue/cache/session provide the application infrastructure.

## Out of scope

AI/agents, appointments/bookings/availability/reservations, public booking, payments/POS/inventory/payroll, subscriptions/billing/SaaS onboarding/workspaces/multi-tenant operation, mobile apps, separate frontend or backend, microservices/Kubernetes, Redis/Horizon/WebSockets, event sourcing, vector search, and public APIs.

## Scope control

Do not add excluded database tables, dependencies, environment settings, placeholder interfaces, routes, or frontend pages. Historical appointment/availability migration definitions are retained pending safe inspection of migration history and data; this is a data-preservation exception, not product functionality. The root `ARCHITECTURE.md` is authoritative if documentation appears inconsistent.
