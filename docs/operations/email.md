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

Branded HTML emails currently cover staff invitations, password resets, email verification, inbound WhatsApp alerts, customer return intentions, completed visits, uncertain WhatsApp delivery outcomes, and staff access changes. Staff access changes (invitation, activation, suspension, and role assignment) notify active owners and audit-authorized staff by email and in-app notification; the actor is excluded, and delivery is deduplicated by audit record. Other operational alerts go to active staff who have the relevant business permission; account-security messages go to their individual account recipient. These alerts do not yet cover every audit-log event, so the owner should use the in-app audit log and dashboard for the full activity record. Customer message bodies and private notes are deliberately excluded from operational email. In-app notifications remain available alongside email and, when configured and enabled by a user, browser push.

No SMTP account, sender-domain verification, provider credentials, or live delivery has been configured or verified in this repository.
