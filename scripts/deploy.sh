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

snapshot_status=0
snapshot_running_release_as_previous || snapshot_status=$?
if (( snapshot_status == 0 )); then
    previous_available=1
elif (( snapshot_status != 2 )); then
    printf 'Deployment stopped: the currently running release could not be saved for rollback.\n' >&2
    exit 1
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

if (( previous_available == 1 )); then
    printf 'Deployment is healthy. The pre-deployment stable release is saved for `make rollback`.\n'
else
    printf 'Deployment is healthy. No earlier stable release existed, so rollback becomes available after the next successful deployment.\n'
fi
