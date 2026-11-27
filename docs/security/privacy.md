# Customer privacy and data handling

This document explains implemented data-handling controls and remaining
operator responsibilities. It does not establish compliance with Nigerian or
other privacy law. The barbershop owner is responsible for obtaining qualified
privacy/legal advice and setting lawful purposes, notices, retention periods,
and customer-rights procedures for the actual operation.

## Data in the CRM

Customer records may contain names, normalized WhatsApp phone numbers, email,
notes, tags, consent evidence, visit history, stated return intentions,
reviews, and communication records. Staff accounts, access changes, message
delivery metadata, and audit records are also stored. Collect only fields
needed for the CRM workflow and ensure staff know what must not be entered in
notes.

## Consent and messaging

WhatsApp consent is represented as a channel/purpose-specific record with
status, collection source/time, evidence/reference, and withdrawal information.
Automated follow-ups check eligibility when scheduled and again before send.
Inbound explicit opt-out phrases revoke consent and cancel eligible pending
follow-ups; standard STOP and UNSUBSCRIBE remain active. Do not treat possession
of a phone number as permission to message. Configure and operate messages in
accordance with Meta policy and applicable law. See the WhatsApp integration
guides for provider-specific setup; live Meta behavior is not verified here.

## Access, exports, and logs

Customer records are scoped to the single configured barbershop and protected
by granular permissions. Customer exports require the export permission, are
logged, use formula-safe CSV output, and are returned with private/no-store
headers. Audit metadata should be limited to safe operational details; avoid
putting message bodies, tokens, passwords, recovery codes, or unnecessary
personal data into logs or audit fields. Restrict access to application logs,
database dumps, and backups.

Webhook handling persists a normalized, deduplicated event before asynchronous
processing. Non-text media payloads are not retained as message payloads;
normalized customer content is redacted after terminal processing failure and
by the configured daily retention command for expired terminal events. The
deduplication event record/tombstone is retained after payload redaction.
Configure `WHATSAPP_WEBHOOK_PAYLOAD_RETENTION_DAYS` and ensure the Laravel
scheduler is running. This retention rule applies to webhook payloads, not to
all message history or backups.

## Anonymization and deletion

Authorized staff can use the customer anonymization workflow. It cancels
pending/queued schedules, refuses to proceed while a provider send or delivery
reconciliation is in flight, removes direct customer fields, clears notes and
tags, redacts conversation message bodies and internal conversation notes,
redacts review text and customer-linked audit before/after metadata, and keeps
the customer record in anonymized form. Related consent/history and
deduplication/audit records may remain for operational integrity and
traceability. Permanent deletion is guarded where linked consent or operational
history must be retained. Anonymization does not erase data already copied to
backups or external provider systems; those are subject to separate retention
and backup lifecycle procedures.

Before acting on an erasure request, verify the requester's identity, review
business retention obligations, investigate pending/uncertain provider
messages, and follow the application's guarded workflow. Do not manually delete
rows or edit historical migrations to satisfy a request.

## Operator responsibilities

- Publish an accurate privacy notice and explain the purpose and expected
  timing of follow-up messages before recording consent.
- Establish how staff record consent evidence and handle verbal, written,
  imported, and withdrawn consent consistently.
- Set retention periods for customers, conversations, reviews, audit records,
  backups, and provider records; the application does not provide a universal
  retention policy for every data class.
- Restrict database, server, backup, log, and Meta business account access;
  encrypt and protect backups and their keys separately.
- Define a process for access/correction/erasure requests and privacy incidents,
  including how to evaluate notification obligations.
- Review Meta terms, message templates, opt-out handling, and cross-border
  processing implications with appropriate advisers.

## Verification and limits

Tests cover explicit consent grant/withdrawal, opt-out cancellation, consent
checks, authorization on exports, CSV formula neutralization, customer
anonymization/redaction, in-flight-message protection, and webhook payload
redaction/retention. A disposable PostgreSQL backup/restore drill is documented
separately. Production retention configuration, data inventory, operator
procedures, legal basis, privacy notice, provider retention, remote backup
retention, and real customer-data deletion have not been independently
verified. No legal compliance claim is made.
