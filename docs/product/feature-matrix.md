# Feature matrix

| Area | Application evidence | Current status |
|---|---|---|
| Authentication and owner provisioning | Fortify, `app:provision-owner`, account-security routes and tests | Implemented; production mail and provider setup require verification |
| Staff and RBAC | Staff services, permissions config, policies, routes and tests | Implemented; review detailed audit for coverage limits |
| Customers, consent and privacy | Customer/consent commands, controllers, pages and feature tests | Implemented locally; legal retention decisions remain operational |
| Completed visits | Visit commands, after-commit event, history and tests | Implemented locally; not a booking system |
| Return-date attendance | Date-only expected/arrived/missed state, arrival records a visit, missed status can schedule approved follow-up | Implemented locally; no slots, payments or booking calendar |
| WhatsApp and inbox | Official provider client, signature-verified webhooks, conversation routes/pages | Implemented with mocked/local tests; live Meta connection is external and unverified |
| WhatsApp send controls | Master and per-purpose toggles, customer consent stop action, cancellation of pending/queued sends, worker revalidation | Implemented locally; live provider behavior remains unverified |
| Automated follow-up | Persistent schedules, database jobs, scheduler and recovery commands, including missed-return rules | Implemented locally; live delivery is unverified |
| Templates and feedback | Local drafts/provider synchronization workflow, review records/pages | Implemented locally; provider approvals are external |
| Dashboard, analytics and audit | Database-backed queries, protected exports and pages | Implemented locally; metrics definitions and limits are documented in audit |
| Operations and deployment | Docker Compose, health checks, backup tooling and app-image rollback | Scripts and isolated drills have evidence; production deployment/restore unverified |
| Historical appointment schema | Legacy migration definitions only | Retained pending configured-database inspection; no active booking behavior |

This summary is not a substitute for [detailed feature traceability](../audits/feature-traceability.md) or the [implementation audit](../audits/implementation-audit.md). It does not claim production readiness or live external integration.
