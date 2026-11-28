# Transactional email

## Local development

The checked-in `.env.example` selects Postmark's SMTP submission endpoint (`smtp.postmarkapp.com:587`, STARTTLS). Keep the local `.env` mailer set to `log` (or use a local mail catcher) so development does not send messages to real staff. The example credentials and sender are intentionally blank/example values; this configuration does not activate delivery.

## Production setup

1. Create a Postmark server and select its transactional message stream.
2. Verify the sender domain and publish the SPF, DKIM, and return-path DNS records Postmark provides. Configure DMARC for the business domain.
3. Set `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, `MAIL_HOST=smtp.postmarkapp.com`, and `MAIL_PORT=587`.
4. Put the Postmark server token in `MAIL_USERNAME` and `MAIL_PASSWORD` using the deployment secret manager. Never commit credentials.
5. Set `MAIL_FROM_ADDRESS` to an address on the verified business domain, then send a controlled test to an internal mailbox.
6. Monitor provider activity, bounces, suppressions, and delivery errors. An accepted SMTP submission is not proof of final inbox delivery.

Branded HTML emails currently cover staff invitations, password resets, email verification, inbound WhatsApp alerts, customer return intentions, completed visits, uncertain WhatsApp delivery outcomes, staff access changes, and the daily activity digest below. Staff access changes (invitation, activation, suspension, and role assignment) notify active owners and audit-authorized staff by email and in-app notification; the actor is excluded, and delivery is deduplicated by audit record. Other operational alerts go to active staff who have the relevant business permission; account-security messages go to their individual account recipient. Customer message bodies and private notes are deliberately excluded from operational email. In-app notifications remain available alongside email and, when configured and enabled by a user, browser push.

## Daily owner and admin activity digest

The scheduler runs `notifications:dispatch-activity-digest` daily at 07:15 in the application timezone. It queues a summary for the previous calendar day in the active barbershop's timezone. Recipients are active business members with `audit.view`, which includes the owner. Staff without that permission and suspended members are excluded.

The email reports counts grouped by audit action and links to the audit screen. It deliberately excludes actor names, customer identifiers, contact details, IP addresses, audit JSON values, message content, tokens, and credentials. Empty days do not generate an email. A database-backed cache key prevents a repeated daily dispatch, and the unique queue job and deterministic notification IDs provide additional duplicate protection. The queue worker must be running for delivery.

Run `php artisan notifications:dispatch-activity-digest` to enqueue the previous day's digest manually. The command is safe to repeat for the same business date. Email delivery still depends on valid SMTP configuration; local development can inspect messages using the configured log mailer.

No SMTP account, sender-domain verification, provider credentials, or live delivery has been configured or verified in this repository.
