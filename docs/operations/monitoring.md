# Monitoring and health checks

## Health endpoints and operator view

The Nginx container health check requests `http://127.0.0.1/ready`. The
`/ready` endpoint checks database connectivity and requires fresh scheduler and
database-queue worker heartbeats (within three minutes). It returns only a
minimal ready/unavailable status. It does not verify WhatsApp provider
reachability, email delivery, backup availability or restore ability.

Authenticated users with `system.health.view` can open **Operations → System
health**. This view reports:

* Database connectivity.
* Queue driver, number of pending database jobs, failed-job count and worker
  heartbeat.
* Scheduler heartbeat.
* Saved WhatsApp configuration completeness.
* Recent webhook event counts/status.

Health aggregation is intentionally conservative. The queue is degraded when
failed jobs exist, unhealthy when a worker heartbeat is stale, and unknown when
required queue tables or heartbeat data are missing. WhatsApp is marked healthy
only when the saved connection is marked connected, the encrypted token can be
decrypted and is non-empty, the required Meta IDs exist, and the configured
Graph API version is valid. That is configuration health only: it does not
prove the token has not been revoked or that Meta can currently be reached.
Webhook backlog and recent failed processing degrade webhook health.

Heartbeats are written to PostgreSQL once per minute by the scheduler and by a
probe handled by the queue worker. A last-seen time within three minutes is
reported healthy. Missing or stale scheduler/worker heartbeats make `/ready`
return HTTP 503. A queue with failed jobs can still be ready when both required
heartbeats are fresh; the owner health view reports that queue as degraded.
Keep host and database clocks synchronized.

## Host-level checks

On the Docker host, review service state and recent logs:

```sh
docker compose ps
docker compose logs --tail=200 app web queue scheduler postgres
```

The deployment script waits for web health and then checks that queue and
scheduler containers are running. This is a process/container check; the
Operations health screen adds database-backed heartbeat and backlog signals.
Use both when investigating a reported issue.

Sentry is optional and is configured through `SENTRY_LARAVEL_DSN`,
`SENTRY_ENVIRONMENT`, `SENTRY_SAMPLE_RATE`, and `SENTRY_TRACES_SAMPLE_RATE`.
The repository's configured data scrubbing should be reviewed before enabling
it with customer traffic. An empty DSN means external Sentry delivery has not
been configured. The scheduler sends in-app, queued email and optional browser
push notifications to active staff with `system.health.view` when monitored
components degrade, worsen or recover. Repeated states are suppressed. These
application alerts require a functioning database/cache and scheduler; email
and push wait for the queue worker. A separate host-level monitor is still
needed to detect a fully stopped database or scheduler.

## Incident triage

1. Check whether `/ready` and the database container are healthy.
2. Check `docker compose ps` and logs for the affected app, queue or scheduler
   service.
3. Review the owner-only Operations health page for heartbeat age, failed and
   pending work, webhook backlog and WhatsApp configuration status.
4. Follow [queue worker operations](queue-workers.md) or
   [scheduler operations](scheduler.md) for component-specific recovery.
5. Preserve logs and relevant record IDs while avoiding access tokens, recovery
   codes and unnecessary customer message contents.
6. If deployment recovery is needed, use the documented deployment procedure;
   `make rollback` leaves the database schema and data unchanged.

## Verification boundary

Repository tests cover health calculations for database failures and incomplete
WhatsApp configuration, health transition deduplication and authorization, plus
deployment health-check control flow. They do not prove production monitoring
uptime, external alert delivery, Meta token validity,
Cloudflare/TLS behavior, or operational response times. `/ready` and the
Operations page must not be described as a production monitoring service until
they are deployed and observed in the target environment.
