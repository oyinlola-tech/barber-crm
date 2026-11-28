# Browser push notifications

Browser push adds a device-level notification beside the existing in-app and email channels. Each staff member must enable it for each browser/device. It requires a secure HTTPS origin (localhost is accepted for development), a working database queue worker, and VAPID credentials.

## Configure

1. Run `php artisan app:web-push-vapid-keys` in a private terminal or deployment environment.
2. Store the public key as `WEBPUSH_VAPID_PUBLIC_KEY` and the private key as `WEBPUSH_VAPID_PRIVATE_KEY` in the deployment secret store. Set `WEBPUSH_VAPID_SUBJECT` to a monitored `mailto:` address or the CRM origin URL.
3. Deploy the environment values, run additive migrations with `php artisan migrate --force`, and restart queue workers.
4. Visit **Account security → Browser notifications** on HTTPS and enable notifications. The browser will request permission and register that device.

The private key is never shared with Inertia or browser code. The browser subscription endpoint and encryption keys are encrypted at rest; endpoint hashes are used for uniqueness. Endpoints are restricted to known browser push services and requests do not follow redirects. Push previews omit customer names, phone numbers, return dates and message content. Clicking a push opens the relevant CRM screen after sign-in.

To disable a device, use **Turn off on this device** in Account security or revoke notifications in the browser settings. Removing a staff account deletes that user’s subscriptions. Expired push endpoints are removed when the provider returns HTTP 404 or 410.

## Delivery behavior

The Laravel notification creates in-app and configured email notifications and enqueues browser push after commit. A database queue worker delivers push with bounded queue retries. No push is sent if VAPID credentials are absent. Browser permission is separate from the CRM account; operating systems may suppress notifications, and browsers may expire subscriptions. Push is therefore an additional channel and does not replace the in-app notification history or email.

Push payloads contain only a short title, generic body and same-origin path. Never add customer message text or private customer details to the push payload.

## Local verification

`http://localhost` is treated as a secure context by modern browsers. Plain HTTP on a LAN address is not; use local HTTPS for testing from another device. Automated tests use a delivery test double and never send real push requests.
