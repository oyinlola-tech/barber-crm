#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"
source "$PROJECT_ROOT/scripts/lib/deployment.sh"

if (( $# > 0 )); then
    printf 'Usage: %s\n' "$0" >&2
    exit 2
fi

previous_available=0
if previous_stable_release_available; then
    previous_available=1
fi

if ! docker compose up --build --detach app web queue scheduler; then
    recover_previous_release "$previous_available" || true

    exit 1
fi

if ! wait_for_web; then
    recover_previous_release "$previous_available" || true

    exit 1
fi

if ! verify_background_services; then
    printf 'Deployment failed: the web service is healthy, but one or more background services are unavailable.\n' >&2
    recover_previous_release "$previous_available" || true

    exit 1
fi

if ! tag_current_release_as_previous; then
    printf 'Deployment is running, but the last known stable image pair could not be recorded for rollback.\n' >&2
    exit 1
fi

printf 'Deployment is healthy. The previous stable release is retained in Docker as barber-crm-app:previous-stable and barber-crm-web:previous-stable.\n'
