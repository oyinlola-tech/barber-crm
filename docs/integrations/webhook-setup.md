# WhatsApp webhook setup

This checklist configures Meta to deliver WhatsApp Business Platform events to
the CRM. It does not activate a Meta app, verify the barbershop, or prove live
delivery.

## Prerequisites

- Deploy the application behind a publicly reachable HTTPS origin with a valid
  TLS certificate. The callback endpoint is `https://<crm-host>/webhooks/whatsapp`.
- Set `WHATSAPP_APP_SECRET` and a long, random `WHATSAPP_VERIFY_TOKEN` in the
  server's secret-managed environment. The verification token is separate from
  the WhatsApp access token.
- Connect and validate the barbershop's WhatsApp Business Account and phone
  number under **Settings → WhatsApp** before expecting incoming events to be
  associated with CRM records.

## Meta App Dashboard

1. Open the Meta app that owns the connected WhatsApp Business integration.
2. In the app's **Webhooks** configuration, select the **WhatsApp Business
   Account** object.
3. Enter `https://<crm-host>/webhooks/whatsapp` as the callback URL and enter
   the exact `WHATSAPP_VERIFY_TOKEN` value as the verify token.
4. Complete callback verification. Meta sends a GET request with
   `hub.mode=subscribe`, `hub.verify_token`, and `hub.challenge`. The CRM checks
   the token with a timing-safe comparison and returns the challenge as plain
   text only when it matches.
5. Subscribe the WhatsApp Business Account to the `messages` webhook field so
   the CRM receives incoming messages and message delivery status updates.
   WhatsApp webhook subscriptions are configured in the Meta App Dashboard;
   do not use the generic Graph API app-subscriptions endpoint for this setup.
6. Use Meta's dashboard test delivery, then verify the event appears in the
   CRM's authorized Operations health and conversation views. Test events do
   not establish that customer production traffic is enabled.

For POST notifications, Meta sends `X-Hub-Signature-256`. The application
validates the `sha256=` HMAC against the unmodified request body using
`WHATSAPP_APP_SECRET`; it rejects missing or invalid signatures before
processing the payload. Valid events are normalized, deduplicated and persisted
before background processing. The route is rate-limited and only this explicit
webhook route is exempted from browser-session CSRF verification.

The endpoint verifies the configured phone-number ID and WhatsApp Business
Account ID before associating inbound events. Duplicate deliveries are safe to
retry. Provider events may arrive out of order; message history is retained
without regressing the current delivery or conversation activity state.

## Verification and troubleshooting

- A failed GET verification usually means the URL, HTTPS certificate, or
  `WHATSAPP_VERIFY_TOKEN` does not match.
- A POST returning 401 usually means `WHATSAPP_APP_SECRET` is missing or does
  not match the Meta app that signed the event.
- A POST returning 413 exceeds `WHATSAPP_WEBHOOK_MAX_BYTES`; 400 indicates
  malformed JSON; 422 indicates an unsupported or invalid payload shape.
- Check that the app has the correct WhatsApp product/use case and that the
  connected phone-number ID and business-account ID match the incoming event.
- Confirm the queue worker is running before troubleshooting delayed inbox
  processing. The HTTP callback persists events before dispatching work.
- Do not share webhook payloads, access tokens, app secrets, or customer
  message contents in support logs.

The webhook callback, signature check, and event handling are covered with
controlled test payloads and HTTP tests. Meta dashboard configuration,
business approval, webhook delivery from Meta, and live customer messaging
remain external verification requirements.

## Official Meta documentation

- [WhatsApp Business Platform overview](https://developers.facebook.com/documentation/business-messaging/whatsapp/overview/)
- [Meta Webhooks setup and signature validation](https://developers.facebook.com/docs/graph-api/webhooks/getting-started/)
- [WhatsApp Business Account reference](https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/)
- [WhatsApp Business Account message templates](https://developers.facebook.com/docs/graph-api/reference/whats-app-business-account/message_templates/)
