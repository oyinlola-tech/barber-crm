# Main data relationships

- A `Business` has staff memberships and business-owned customers, services, settings, WhatsApp connection, templates, visits, conversations, automation rules, scheduled messages, reviews, and audit records.
- `BusinessMember` links a user to the one configured shop and carries the active role/status used by authorization. `StaffProfile` and `StaffInvitation` support employee lifecycle.
- A customer has consent records, notes, tags, completed visits, feedback, and at most one relevant WhatsApp conversation per configured uniqueness rules.
- A conversation belongs to a customer and contains inbound/outbound messages, optional assignments, and internal notes. Messages can link to durable scheduled messages and provider identifiers; message events record provider delivery state.
- A webhook event is deduplicated by provider/event key. A status `MessageEvent` retains the minimal provider message identifier needed to reconcile callbacks that precede their outbound message row. Raw webhook payloads are redacted according to retention configuration while the dedupe record remains.
- A completed visit can produce automation executions and scheduled messages. Eligibility and consent are rechecked before delivery. Reviews and review requests link feedback to the customer and, when known, the visit/message workflow.

Foreign keys, unique keys and indexes in migrations are authoritative. This summary intentionally does not describe the retained historical appointment/availability tables as active product relationships. No configured or production database was inspected as part of this document.
