# Queue worker operations

The production Compose stack uses Laravel's database queue. The `queue` service
runs the `barber-crm-app:current` runtime image and shares the PostgreSQL and
Laravel storage volumes with the application. It starts:

```sh
php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600
```

`DB_QUEUE_RETRY_AFTER` defaults to 90 seconds, which is longer than the worker's
60-second timeout. Preserve that ordering if either value changes. The worker
exits after an hour; Compose's `restart: unless-stopped` policy starts it again.
This periodically recycles the PHP process. It does not replace job idempotency
or failed-job review.

## Inspect and restart

Run these from the repository directory on the Docker host:

```sh
docker compose ps queue
docker compose logs --tail=200 queue
docker compose restart queue
```

After deploy, `make deploy` starts and checks the queue service along with web and
scheduler. For a deliberate rollback, `make rollback` restores the previous
stable application images and checks that the worker is running. Neither
operation changes PostgreSQL data or migrations.

The owner-only Operations health page reports database queue pending and failed
counts plus the last successful worker heartbeat. A scheduled probe is placed
on the database queue every minute; the worker records its heartbeat when it
processes that probe. A missing/stale heartbeat (older than three minutes) is
unhealthy. A pending job count alone is not an error, but pending jobs with a
missing or stale worker heartbeat make queue health unhealthy. Any failed queue
jobs make the queue degraded.

## Failed work and message safety

Inspect the failed job records and worker logs before taking action:

```sh
docker compose exec app php artisan queue:failed
docker compose logs --since=30m queue app
```

Do not use `queue:retry all` as a general WhatsApp recovery step. A worker may
have sent a request that Meta accepted even if the application did not receive
the response. Blind replay can duplicate customer messages. Scheduled-message
records distinguish safely retryable pre-send work from delivery-uncertain work;
use the CRM's message operations and reconciliation state, and retry only when
the UI/backend permits it. Review individual failed job payloads carefully and
avoid copying customer content or credentials into tickets or logs.

The `automations:recover-stalled-messages` command runs every five minutes. It
requeues safe claims that stalled before provider dispatch and marks sends that
may have crossed the provider boundary for reconciliation rather than blind
resend. For a growing queue, first check worker status, database connectivity,
recent application deploys and provider error logs. Do not delete rows from
`jobs` or `failed_jobs` to make health counts look better.

## Verification boundary

Compose configuration, worker command settings, operational heartbeat logic,
and deployment/rollback control flow have been inspected and locally tested.
The worker has not been monitored on a production host, and real Meta delivery,
provider reconciliation, production queue load, and recovery-time objectives
remain unverified.
