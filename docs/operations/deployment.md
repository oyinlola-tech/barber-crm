# Docker deployment notes

Compose runs PostgreSQL, the PHP-FPM application, Nginx, a database queue worker,
and Laravel's scheduler. The web port binds to `127.0.0.1:8000` by default so
plain HTTP is not exposed directly to the network. Put a configured TLS reverse
proxy in front of it. Set `WEB_BIND_ADDRESS` only when the host networking and
TLS/edge controls are intentionally configured.

## First deployment

1. Create a protected production `.env` with `APP_ENV=production`,
   `APP_DEBUG=false`, a generated `APP_KEY`, strong database credentials,
   `APP_URL` set to the HTTPS origin, `SESSION_SECURE_COOKIE=true`, and the
   required mail/Sentry settings. Set `TRUSTED_PROXIES` to the exact proxy IPs
   or CIDRs that connect to Laravel; never trust arbitrary forwarded headers
   from the public internet. Keep `.env` outside source control and restrict
   file access.
   The application applies a same-origin Content Security Policy, frame and
   MIME protections, referrer and permissions policies, and enables HSTS only
   on secure requests. Tune HSTS with `SECURITY_HSTS_MAX_AGE` and
   `SECURITY_HSTS_INCLUDE_SUBDOMAINS`; use subdomain coverage only when every
   subdomain is HTTPS-ready. HSTS is not a replacement for TLS configuration.
2. Deploy with `make deploy`. The script snapshots the currently running app
   and web images as `previous-stable`, builds the new `current` images, starts
   the app, web, queue and scheduler, waits for the web health check, then
   verifies the queue worker and scheduler containers are running. If web
   health fails or a background service is stopped, deployment automatically
   restores the saved images and rechecks the web service, queue worker, and
   scheduler. It returns a failure code so the attempted release is not
   mistaken for a successful deploy. If recovery cannot be verified, inspect
   the emitted service logs and run `make rollback` after resolving the cause.
   On a fresh host with no existing images, the first deployment has no earlier
   release to roll back to; later deployments create the snapshot.
3. After verifying the target database and backup state, apply forward
   migrations with `docker compose exec app php artisan migrate --force`.
   Never use migration reset, fresh, or rollback commands against production.
4. Provision the initial owner interactively with
   `docker compose exec app php artisan app:provision-owner`.
5. Confirm `https://your-host/ready` through the intended TLS proxy and check
   the owner-only Operations health screen. A healthy readiness response checks
   application-to-database connectivity only; it does not verify WhatsApp,
   workers, scheduler, email delivery, or backup recovery.

The queue container uses a 60-second worker timeout while Laravel's database
queue retry-after defaults to 90 seconds. Keep the worker timeout shorter than
`DB_QUEUE_RETRY_AFTER` if either value is changed, to reduce the risk of another
worker reserving a still-running job. The worker also exits after one hour and
Docker restarts it under the `unless-stopped` policy. This periodically releases
long-lived PHP process memory; in-flight jobs still follow the configured job
timeout and retry behavior.

## Operations and limitations

Use `docker compose logs --tail=200 app queue scheduler web` for service logs.
Restart long-lived workers after deploying new code with
`docker compose restart queue scheduler`; deploy through a reviewed release
procedure and preserve persistent volumes. Do not publish the PostgreSQL port.

Run `make rollback` to restore the previous stable app and web images. It takes
no version argument, restarts the queue worker and scheduler with those images,
and verifies both are running before reporting success. A service startup or
worker/scheduler check failure is reported with service logs. Rollback leaves
PostgreSQL, migrations, customer data, and volumes untouched. Database
migrations must remain backward-compatible with the previous release; do not
run `migrate:rollback` as part of application rollback. If a schema change is
not compatible, restore from a verified backup through a separately reviewed
recovery plan.

Compose persists PostgreSQL and Laravel storage in local Docker volumes. The
repository includes encrypted backup and restore-to-new-database scripts; see
[`backup-and-restore.md`](backup-and-restore.md) for secret handling, commands,
and scope. An isolated PostgreSQL 17 backup/restore drill passed, but no
production deployment, remote backup copy, retention policy, live health
monitoring, production backup, or production restore has been verified. Before
production use, configure protected offsite storage and retention, preserve the
secret in a recoverable secret manager, and schedule recurring restore drills.
