# Operations troubleshooting

Run commands from the repository directory on the Docker host. Avoid destructive
database, volume or queue-table commands during diagnosis. Preserve business
data and inspect logs/status before restarting services.

## Web is unavailable or `/ready` fails

```sh
docker compose ps
docker compose logs --tail=200 web app postgres
```

The web container's `/ready` probe requires the Laravel app to answer and reach
PostgreSQL. Check the `app` and `postgres` services, configured `.env` database
values, and recent migration/deploy output. `/ready` only represents web plus
database readiness; it says nothing about the worker, scheduler or Meta.

## Follow-ups are not being sent

1. Open the owner-only Operations health view. Check the queue and scheduler
   states, pending jobs, worker heartbeat, and WhatsApp configuration status.
2. On the host, inspect:

   ```sh
   docker compose ps queue scheduler
   docker compose logs --since=30m queue scheduler app
   docker compose exec app php artisan queue:failed
   ```

3. Confirm the automation feature is enabled, the relevant rule is active, the
   customer has valid consent, the template is approved/enabled, and the
   scheduled time is within allowed sending hours and the business timezone.
4. Inspect the scheduled-message state. A `cancelled`, `skipped`, `failed`, or
   delivery-uncertain/reconciliation state has a different remedy than a
   pending schedule.
5. Restore queue/scheduler processes if stopped. Do not manually resend a
   delivery-uncertain item until provider outcome has been reconciled.

The worker health probe is itself a database queue job. A running container
without a fresh heartbeat is not sufficient evidence that jobs are being
processed.

## Queue pending count is growing

Check worker logs and `docker compose ps queue`; then check PostgreSQL health and
the Operations screen's heartbeat timestamp and failed count. The normal worker
polls with a three-second sleep and a job timeout of 60 seconds. If queue
connectivity is restored, allow the worker to drain rather than deleting queue
rows. If individual jobs fail, inspect each outcome; WhatsApp jobs can require
reconciliation and are not all safe to replay.

## Scheduler heartbeat is stale

Check `docker compose ps scheduler` and scheduler logs. Confirm that exactly the
intended scheduler process (`php artisan schedule:work`) is active and can reach
the database. Restart the scheduler service only after capturing the relevant
logs. The heartbeat updates each minute, and the health page considers it
healthy only when no older than three minutes.

## WhatsApp is shown as degraded

The local status check verifies the saved connection flag, decryptability and
presence of the access token, phone-number ID, business-account ID and valid
Graph API version. Check the connection configuration through the authorized
settings flow and inspect redacted application logs. The status cannot detect
token revocation or prove provider reachability; perform an authorized
connection validation from the application. Do not paste tokens into command
arguments, tickets or logs.

## Webhooks are delayed or failing

Review the owner-only health view for unprocessed and recent failed events, then
inspect application and queue logs. Confirm the public webhook URL and Meta
configuration using [webhook setup](../integrations/webhook-setup.md). Do not
disable signature verification to make delivery succeed. Duplicate provider
events are expected and should remain deduplicated. Old events can arrive late;
avoid reverting newer delivery/conversation state based on stale callbacks.

## Deployment or rollback fails

Read the failure output and service logs first:

```sh
docker compose logs --tail=200 app web queue scheduler
```

`make deploy` automatically restores retained previous app/web images when
post-start checks fail. `make rollback` manually restores those image tags; it
does not revert migrations, database contents or volumes. If there is no
previous stable image on a new host, rollback is unavailable until a successful
deployment has created the retained tags. If rollback health checks fail, do
not repeatedly switch versions: preserve logs, identify the failed service and
use the documented deployment runbook. Database schema changes must remain
compatible with the prior code release.

## Backup or restore concern

Follow [backup and restore](backup-and-restore.md). Restore drills target a new
database and do not cut over the app. Never point the provided restore script at
the configured production database or delete a volume to recover from a failed
restore. Production backup and offsite-copy procedures need to be established
and exercised separately.

## Verification boundary

These steps reflect checked-in Compose files, commands, health calculations and
runbooks. Local deployment/rollback control-flow tests do not prove a specific
production incident response, recovery time, external monitoring alert or live
WhatsApp operation.
