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
if tag_running_release_as_previous; then
    previous_available=1
fi

if ! docker compose up --build --detach app web queue scheduler; then
    if (( previous_available )); then
        restore_previous_release_tags
        start_current_release
        wait_for_web || true
    fi
    exit 1
fi

if ! wait_for_web; then
    if (( previous_available )); then
        printf 'Restoring the previous stable application images.\n' >&2
        restore_previous_release_tags
        start_current_release
        wait_for_web || true
    else
        printf 'No prior stable release was available for automatic recovery.\n' >&2
    fi
    exit 1
fi

printf 'Deployment is healthy. The previous stable release is retained in Docker as barber-crm-app:previous-stable and barber-crm-web:previous-stable.\n'
