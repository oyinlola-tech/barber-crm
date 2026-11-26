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
    printf 'No previous stable release is recorded. Deploy successfully with scripts/deploy.sh first.\n' >&2
    exit 1
fi

docker compose stop queue scheduler
restore_previous_release_tags
start_current_release

if ! wait_for_web; then
    printf 'Rollback images were started, but the web health check failed. Inspect the service logs before further changes.\n' >&2
    exit 1
fi

printf 'Rolled back to the previous stable application images. The database and migrations were left unchanged.\n'
