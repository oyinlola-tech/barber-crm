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
   Database-backed Laravel sessions are encrypted at rest by default through
   `SESSION_ENCRYPT=true`; retain this setting in production.
   Enabling encryption for an existing deployment makes older plaintext
   session rows unreadable, so users may need to sign in again after rollout;
   customer and business records are unaffected.
2. Build the application image, start only PostgreSQL, and verify which
   configured database and persistent volume it will use. Do not remove or
   recreate volumes to make this check pass:

    ```sh
    docker compose build app
    docker compose up -d postgres
    docker compose ps postgres
    docker compose exec -T postgres sh -ec 'pg_isready -U "$POSTGRES_USER" -d "$POSTGRES_DB"'
    docker compose exec -T postgres sh -ec 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select current_database()"'
    docker compose exec -T postgres sh -ec 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Atc "select count(*) from pg_catalog.pg_tables where schemaname = current_schema()"'
    docker inspect "$(docker compose ps -q postgres)" --format '{{range .Mounts}}{{.Name}} -> {{.Destination}}{{println}}{{end}}'
    ```

    On a production database that may already contain business data, review its
    identity and migration status before proceeding. For an existing database,
    inspect migration state with:

    ```sh
    docker compose run --rm --no-deps app php artisan migrate:status
    ```

    Create and review a recent encrypted backup before any migration: run
    `docker/backup/backup.sh` with
    `BACKUP_ENCRYPTION_PASSPHRASE` injected from the approved secret manager, and
    verify the resulting artifact and required offsite copy. Do not put the
    passphrase in `.env`, source control, or shell history. For a genuinely new
    empty database, verify that the table count is zero and that the selected
    database and volume are the intended new target; a missing migration table
    is expected on that new database.

3. Apply migrations manually, only after the database and backup review gate
   above is satisfied:

    ```sh
    docker compose run --rm --no-deps app php artisan migrate --force
    docker compose run --rm --no-deps app php artisan migrate:status
    ```

    This one-off Compose command uses the built application image and the
    configured PostgreSQL service without starting the web, queue, or scheduler.
    Never use migration reset, fresh, rollback, or destructive volume commands
    against production. Do not make deployment run migrations automatically.

4. Deploy with `make deploy`. The script builds the new `current` images, starts
   the app, web, queue and scheduler, waits for `/ready`, then verifies the
   queue worker and scheduler containers are running. Readiness requires
   database access plus fresh scheduler and queue-worker heartbeats, which are
   written after the scheduler and worker start. The health-check wait allows
   up to 120 seconds by default. If web health fails or a background service is
   stopped, deployment automatically restores the prior stable images and
   rechecks the web service, queue worker, and scheduler. It returns a failure
   code so the attempted release is not mistaken for a successful deploy. Only
   after these checks pass does deploy save the running pair as
   `previous-stable`; `make rollback` automatically restores that last
   successfully deployed pair. If recovery cannot be verified, inspect the
   emitted service logs. On a fresh host, no prior version exists until the
   first deployment passes its checks.
5. Provision the initial owner interactively with
   `docker compose exec app php artisan app:provision-owner`.
6. Confirm `https://your-host/ready` through the intended TLS proxy and check
   the owner-only Operations health screen. A healthy readiness response
   confirms database connectivity and recent scheduler/queue-worker heartbeats;
   it does not verify WhatsApp provider reachability, email delivery, or backup
   recovery.

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
no version argument and automatically selects the pair saved by the last
successful deployment. It restarts the queue worker and scheduler with those images,
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
