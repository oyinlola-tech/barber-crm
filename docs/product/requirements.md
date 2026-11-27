# Product requirements

The product is a private staff-facing WhatsApp CRM for one existing barbershop. Staff maintain customer records, record completed visits, track explicit WhatsApp consent, manage shared conversations and feedback, and configure bounded rule-based follow-ups. Customers need no CRM login or application.

## Required capabilities

- Private owner/staff authentication, MFA for privileged users, invitations, suspension, and permission-based access.
- Customer search and profile management, normalized international phone numbers, notes/tags, consent history, retention status, and privacy workflows.
- Completed visit history that can trigger consent-checked follow-up workflows.
- Official WhatsApp Cloud API integration, approved template management, inbound webhook processing, shared inbox, and provider delivery tracking.
- Durable, configurable automation with stop conditions, bounded retries, quiet hours, deduplication, and operational recovery.
- Customer feedback/reviews, actual-data dashboard and reports, settings, notifications, audit history, and operations visibility.

## Product exclusions

No AI, appointment or booking workflow, payment/POS, inventory/payroll, subscription/SaaS onboarding, workspace switching, mobile app, separate frontend, Redis/Horizon/WebSockets, or public developer API. A customer's stated return date is stored as customer information and never reserves a slot.

## External prerequisites

Live messaging needs valid Meta credentials, WhatsApp Business configuration and verification as applicable, approved templates, a reachable HTTPS webhook, and provider-side setup. Email, deployment TLS, Sentry, offsite backup, and production restore also need separately configured and verified operations. See [feature status](../audits/feature-traceability.md).
