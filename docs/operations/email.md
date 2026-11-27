# Transactional email

## Local development

The example environment uses Laravel's `log` mailer so local development never sends messages to real staff. Invitation emails can be inspected in the application log. The application defaults SMTP to Postmark's submission endpoint (`smtp.postmarkapp.com:587`, STARTTLS), but this does not activate delivery by itself.

## Production setup

1. Create a Postmark server and select its transactional message stream.
2. Verify the sender domain and publish the SPF, DKIM, and return-path DNS records Postmark provides. Configure DMARC for the business domain.
3. Set `MAIL_MAILER=smtp`, `MAIL_SCHEME=tls`, `MAIL_HOST=smtp.postmarkapp.com`, and `MAIL_PORT=587`.
4. Put the Postmark server token in `MAIL_USERNAME` and `MAIL_PASSWORD` using the deployment secret manager. Never commit credentials.
5. Set `MAIL_FROM_ADDRESS` to an address on the verified business domain, then send a controlled test to an internal mailbox.
6. Monitor provider activity, bounces, suppressions, and delivery errors. An accepted SMTP submission is not proof of final inbox delivery.

The application currently provides branded HTML templates for staff invitations, inbound WhatsApp alerts, and completed-visit activity. Customer message bodies and private notes are deliberately excluded from operational email. In-app notifications remain available. Password reset and verification notifications use Laravel Fortify's current notifications until a branded implementation is verified; do not assume every framework notification uses the custom email layout.

No SMTP account, sender-domain verification, provider credentials, or live delivery has been configured or verified in this repository.
