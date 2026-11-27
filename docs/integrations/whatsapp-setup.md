# WhatsApp Cloud API setup

The CRM connects to one barbershop-owned WhatsApp Business Platform account. It does not perform Meta onboarding or business verification.

## Server configuration

Set `WHATSAPP_API_VERSION` to a Graph API version currently supported by Meta. Keep `WHATSAPP_APP_SECRET` and `WHATSAPP_VERIFY_TOKEN` in the server secret store. Do not put access tokens in `.env.example`, source control, browser properties or logs.

Webhook request bodies are normalized before persistence; provider media payloads are not retained or downloaded. Successfully processed events discard their normalized payload, and terminally failed events are redacted immediately. The daily `webhooks:redact-expired-payloads` task also redacts payloads from old terminal events after `WHATSAPP_WEBHOOK_PAYLOAD_RETENTION_DAYS` (30 days by default). It retains event IDs and status rows as deduplication tombstones, so a late duplicate provider delivery cannot be reprocessed after payload expiry. Received or otherwise nonterminal events are preserved for processing and operational recovery.

The owner configures the phone number ID, WhatsApp Business Account ID and a system-user access token from **Settings → WhatsApp**. The app calls Meta to verify the phone number, verify the business account and confirm the phone number is associated with that account before it stores anything. The bearer token is encrypted in the database and never returned to the browser.

The token must be allowed to read the business account and its phone numbers, and it must have messaging permissions for the app's sending and template workflows. Follow Meta's current permission requirements for the business setup in use.

## Official Meta references

- [WhatsApp Business Management API guides](https://developers.facebook.com/docs/whatsapp/business-management-api/guides/)
- [Manage WhatsApp Business Account phone numbers](https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/phone_numbers/)
- [WhatsApp Business Account object](https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/)
- [WhatsApp Business Platform overview](https://developers.facebook.com/documentation/business-messaging/whatsapp/overview/)
- [WhatsApp Business Account message templates](https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/message_templates/)

Meta's dashboard activation, token issuance, account verification, webhook subscription and template approval remain external setup requirements. Automated tests use HTTP fakes; they do not prove live Meta access or message delivery.

## Replacing or disconnecting credentials

Submitting new credentials verifies them before replacing the stored connection. If verification fails, the submitted credentials are not saved and the existing connection record is left intact. A privileged staff member can disconnect the integration; this clears the stored token and records the action in the audit log. Reconnecting is required before outgoing messages can resume.
