# Product scope

## In scope

One private CRM installation for a barbershop. Authorized staff manage customers, consent, completed visits, WhatsApp conversations, deterministic follow-up messages, templates, feedback, reporting, business settings, security, and audit records. PostgreSQL and Laravel's database-backed queue/cache/session provide the application infrastructure. Customers may state a preferred return date through a WhatsApp reply or explicit date request; this is a non-reserving visit intention that alerts staff and never creates a time slot, appointment, calendar entry, or payment. Staff can mark the date arrived or missed; arrival records a completed visit and a missed date can schedule a message only through a dedicated enabled automation rule and active consent.

## Out of scope

AI/agents, appointment scheduling, time-slot bookings, availability/reservations, public booking portals, payments/POS/inventory/payroll, subscriptions/billing/SaaS onboarding/workspaces/multi-tenant operation, mobile apps, separate frontend or backend, microservices/Kubernetes, Redis/Horizon/WebSockets, event sourcing, vector search, and public APIs. A customer’s date-only visit intention is allowed as customer communication data; it is not a booking or reservation.

## Scope control

Do not add excluded database tables, dependencies, environment settings, placeholder interfaces, routes, or frontend pages. Historical appointment/availability migration definitions are retained pending safe inspection of migration history and data; this is a data-preservation exception, not product functionality. The root `ARCHITECTURE.md` is authoritative if documentation appears inconsistent.
