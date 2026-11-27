# Scheduler operations

The Compose `scheduler` service uses the same application image, `.env`,
PostgreSQL connection and persistent Laravel storage as the web and queue
services. It runs Laravel's long-lived scheduler loop:

```sh
php artisan schedule:work
```

Do not also install a host cron entry for this Compose deployment. Running a
second scheduler instance can make scheduled tasks contend; `withoutOverlapping`
reduces that risk for the tasks that use it, but the intended setup is one
scheduler service.

## Registered schedule

`routes/console.php` currently registers:

| Frequency | Task | Purpose |
| --- | --- | --- |
| Every minute | `automations:dispatch-due-messages` | Claims and queues up to 100 due messages. |
| Every five minutes | `automations:recover-stalled-messages` | Requeues safe pre-send claims and flags potentially sent messages for reconciliation. |
| Hourly | `automations:schedule-inactive-customers` | Schedules eligible inactive-customer follow-ups, subject to current rule/consent settings. |
| Every minute | `operations:record-heartbeat` | Records the scheduler heartbeat and enqueues a worker heartbeat probe. |
| Every minute | `webhooks:reconcile-delivery-statuses` | Reconciles delivery callbacks received before the matching outbound message record, up to 200 per run. |
| Daily at 02:30 | `webhooks:redact-expired-payloads` | Redacts expired stored webhook payloads while retaining deduplication records. |

The automation settings default to disabled unless `AUTOMATIONS_ENABLED` is
enabled. Check business rules, consent and approved templates before enabling
automated sending. Frequencies are scheduler polling frequencies, not promises
that a message will be sent at an exact second; allowed sending hours,
eligibility checks and queue delay also apply.

## Inspect and operate

```sh
docker compose ps scheduler
docker compose logs --tail=200 scheduler
docker compose exec app php artisan schedule:list
docker compose restart scheduler
```

The Operations health page shows the latest scheduler heartbeat. Heartbeats
older than three minutes are unhealthy. `/ready` is a separate web readiness
probe and checks database connectivity only; it does not establish that the
scheduler is running.

If scheduled work stops, inspect scheduler logs and health first, then verify
the database and queue services. Check that `schedule:work` is active and that
the current release was deployed to all services. Restart the scheduler after
deployment if the service still runs old code. Do not invoke a scheduled
command repeatedly as a substitute for understanding its idempotency and
effects. For message dispatch, inspect the persisted scheduled-message status
before any manual action.

## Verification boundary

The schedule definitions, Docker service command, and heartbeat implementation
are present in the repository. Local automated checks cover operational health
and deployment control flow. Scheduler timing and recovery have not been
observed on a production host; production clock synchronization, task latency,
and message delivery are not verified here.
