#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"
source "$PROJECT_ROOT/scripts/lib/deployment.sh"

if (( $# > 0 )); then
    printf 'Usage: %s\n' "$0" >&2
    exit 2
fi

if ! docker image inspect barber-crm-app:previous-stable >/dev/null 2>&1 \
    || ! docker image inspect barber-crm-web:previous-stable >/dev/null 2>&1; then
    printf 'Cannot roll back: no previous stable release is available. Run a successful deployment first.\n' >&2
    exit 1
fi

# Prepare both image aliases before stopping any services. A failed Docker tag
# must leave the running release untouched.
if ! restore_previous_release_tags; then
    printf 'Rollback failed: the saved stable image pair could not be selected. Running services were left untouched.\n' >&2
    exit 1
fi

# Stop the workers before switching the app image so they do not keep running
# code from the failed release during the rollback.
docker compose stop queue scheduler
if ! start_current_release; then
    printf 'Rollback failed: the application, web, queue, or scheduler service could not be started. Inspect the service logs before further changes.\n' >&2
    docker compose logs --tail=100 app web queue scheduler >&2 || true
    exit 1
fi

if ! wait_for_web; then
    printf 'Rollback images were started, but the web health check failed. Inspect the service logs before further changes.\n' >&2
    exit 1
fi

for service in queue scheduler; do
    if [[ -z "$(docker compose ps --status running -q "$service")" ]]; then
        printf 'Rollback failed: the %s service is not running. Inspect the service logs before further changes.\n' "$service" >&2
        docker compose logs --tail=100 app web queue scheduler >&2 || true
        exit 1
    fi
done

printf 'Rolled back to the previous stable application images; web, queue, and scheduler are running. The database and migrations were left unchanged.\n'
