#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
rg -q '"--max-time=3600"' "$PROJECT_ROOT/compose.yaml"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/barber-crm-deploy-test.XXXXXX")"
trap 'rm -rf -- "$TEMP_DIR"' EXIT
mkdir -p "$TEMP_DIR/bin"
export FAKE_DOCKER_LOG="$TEMP_DIR/docker.log"
export FAKE_HEALTH_SEQUENCE="$TEMP_DIR/health-sequence"
export FAKE_HEALTH_COUNT="$TEMP_DIR/health-count"
export FAKE_RECOVERY_STARTED="$TEMP_DIR/recovery-started"
export FAKE_PREVIOUS_APP_IMAGE="$TEMP_DIR/previous-app-image"
export FAKE_PREVIOUS_WEB_IMAGE="$TEMP_DIR/previous-web-image"
printf 'sha256:stable-app\n' > "$FAKE_PREVIOUS_APP_IMAGE"
printf 'sha256:stable-web\n' > "$FAKE_PREVIOUS_WEB_IMAGE"

cat > "$TEMP_DIR/bin/docker" <<'DOCKER_STUB'
#!/usr/bin/env bash
set -Eeuo pipefail
printf '%s\n' "$*" >> "$FAKE_DOCKER_LOG"

if [[ "$1" == compose ]]; then
    shift
    if [[ "$1" == up && "${FAKE_COMPOSE_UP_FAIL:-0}" == 1 ]]; then
        exit 1
    fi
    if [[ "$1" == up && " ${*} " == *" --no-build "* && "${FAKE_ENABLE_RECOVERY_STUB:-0}" == 1 ]]; then
        touch "$FAKE_RECOVERY_STARTED"
    fi
    if [[ "$1" == ps ]]; then
        service="${*: -1}"
        if [[ " ${*} " == *" --status running "* && "${FAKE_NO_RUNNING:-0}" == 1 \
            && ( "$service" == app || "$service" == web ) ]]; then
            exit 0
        fi
        stopped_services="${FAKE_STOPPED_SERVICES:-}"
        if [[ -f "$FAKE_RECOVERY_STARTED" ]]; then
            stopped_services="${FAKE_RECOVERY_STOPPED_SERVICES:-}"
        fi
        if [[ " ${*} " == *" --status running "* \
            && " $stopped_services " == *" ${service} "* ]]; then
            exit 0
        fi
        printf '%s\n' "${service}-container"
        exit 0
    fi
    exit 0
fi

if [[ "$1" == inspect && "$2" == --format ]]; then
    if [[ "$3" == "{{.Image}}" ]]; then
        case "$4" in
            app-container) printf 'sha256:old-app\n' ;;
            web-container) printf 'sha256:old-web\n' ;;
            *) exit 1 ;;
        esac
        exit 0
    fi

    count=0
    [[ -f "$FAKE_HEALTH_COUNT" ]] && count="$(cat "$FAKE_HEALTH_COUNT")"
    printf '%s' "$((count + 1))" > "$FAKE_HEALTH_COUNT"
    status=healthy
    if [[ -f "$FAKE_HEALTH_SEQUENCE" ]]; then
        status="$(sed -n "$((count + 1))p" "$FAKE_HEALTH_SEQUENCE")"
        status="${status:-healthy}"
    fi
    printf '%s\n' "$status"
    exit 0
fi

if [[ "$1" == image && "$2" == inspect ]]; then
    if [[ " ${*} " == *" --format "* ]]; then
        case "${*: -1}" in
            barber-crm-app:current) printf 'sha256:built-app\n' ;;
            barber-crm-web:current) printf 'sha256:built-web\n' ;;
            barber-crm-app:previous-stable) cat "$FAKE_PREVIOUS_APP_IMAGE" 2>/dev/null || exit 1 ;;
            barber-crm-web:previous-stable) cat "$FAKE_PREVIOUS_WEB_IMAGE" 2>/dev/null || exit 1 ;;
            *) exit 1 ;;
        esac
        exit 0
    fi
    case "${*: -1}" in
        barber-crm-app:previous-stable) [[ -s "$FAKE_PREVIOUS_APP_IMAGE" ]] ;;
        barber-crm-web:previous-stable) [[ -s "$FAKE_PREVIOUS_WEB_IMAGE" ]] ;;
        *) exit 1 ;;
    esac
    exit $?
fi

if [[ "$1" == tag ]]; then
    if [[ "${FAKE_FAIL_TAG_TARGET:-}" == "${@: -1}" ]]; then
        exit 1
    fi
    case "${@: -1}" in
        barber-crm-app:previous-stable) printf '%s\n' "$2" > "$FAKE_PREVIOUS_APP_IMAGE" ;;
        barber-crm-web:previous-stable) printf '%s\n' "$2" > "$FAKE_PREVIOUS_WEB_IMAGE" ;;
    esac
    exit 0
fi

printf 'Unexpected fake docker invocation: %s\n' "$*" >&2
exit 1
DOCKER_STUB
chmod 700 "$TEMP_DIR/bin/docker"
export PATH="$TEMP_DIR/bin:$PATH"

: > "$FAKE_DOCKER_LOG"
"$PROJECT_ROOT/scripts/deploy.sh" >"$TEMP_DIR/deploy-output"
rg -q 'tag sha256:old-app barber-crm-app:previous-stable' "$FAKE_DOCKER_LOG"
rg -q 'tag sha256:old-web barber-crm-web:previous-stable' "$FAKE_DOCKER_LOG"
! rg -q 'tag sha256:built-(app|web) barber-crm-(app|web):previous-stable' "$FAKE_DOCKER_LOG"
rg -q 'Deployment is healthy. The pre-deployment stable release is saved' "$TEMP_DIR/deploy-output"
rg -q 'compose up --build --detach app web queue scheduler' "$FAKE_DOCKER_LOG"

# A first deployment has no running release to snapshot and should say so
# without inventing a rollback image pair.
: > "$FAKE_DOCKER_LOG"
: > "$FAKE_PREVIOUS_APP_IMAGE"
: > "$FAKE_PREVIOUS_WEB_IMAGE"
export FAKE_NO_RUNNING=1
"$PROJECT_ROOT/scripts/deploy.sh" >"$TEMP_DIR/deploy-output"
rg -q 'No earlier stable release existed' "$TEMP_DIR/deploy-output"
! rg -q 'tag .*barber-crm-(app|web):previous-stable' "$FAKE_DOCKER_LOG"
unset FAKE_NO_RUNNING
printf 'sha256:old-app\n' > "$FAKE_PREVIOUS_APP_IMAGE"
printf 'sha256:old-web\n' > "$FAKE_PREVIOUS_WEB_IMAGE"

# A failure while saving either running image must stop deployment before
# Compose replaces the healthy release.
: > "$FAKE_DOCKER_LOG"
export FAKE_FAIL_TAG_TARGET=barber-crm-web:previous-stable
if "$PROJECT_ROOT/scripts/deploy.sh" >"$TEMP_DIR/deploy-output" 2>&1; then
    printf 'Deployment test expected rollback-image recording failure to fail.\n' >&2
    exit 1
fi
rg -q 'Deployment stopped: the currently running release could not be saved for rollback' "$TEMP_DIR/deploy-output"
if rg -q 'compose up --build --detach app web queue scheduler' "$FAKE_DOCKER_LOG"; then
    printf 'Deployment continued after saving the prior release failed.\n' >&2
    exit 1
fi
unset FAKE_FAIL_TAG_TARGET

# A healthy web probe alone must not make deploy report success while a
# background service is stopped.
: > "$FAKE_DOCKER_LOG"
export FAKE_STOPPED_SERVICES=queue
if "$PROJECT_ROOT/scripts/deploy.sh" >"$TEMP_DIR/deploy-output" 2>&1; then
    printf 'Deployment test expected a stopped queue worker to fail verification.\n' >&2
    exit 1
fi
rg -q 'Deployment failed: the web service is healthy, but one or more background services are unavailable' "$TEMP_DIR/deploy-output"
rg -q 'compose logs --tail=100 app web queue scheduler' "$FAKE_DOCKER_LOG"
if rg -q '^Deployment is healthy\.' "$TEMP_DIR/deploy-output"; then
    printf 'Deployment reported success despite a stopped background service.\n' >&2
    exit 1
fi
unset FAKE_STOPPED_SERVICES

# A failed background-service check should automatically restore the previous
# stable release and recheck its web, queue, and scheduler health.
: > "$FAKE_DOCKER_LOG"
: > "$FAKE_HEALTH_COUNT"
rm -f -- "$FAKE_RECOVERY_STARTED"
export FAKE_STOPPED_SERVICES=queue
export FAKE_ENABLE_RECOVERY_STUB=1
if "$PROJECT_ROOT/scripts/deploy.sh" >"$TEMP_DIR/deploy-output" 2>&1; then
    printf 'Deployment test expected the failed release to return nonzero after recovery.\n' >&2
    exit 1
fi
rg -q 'Deployment failed: the web service is healthy, but one or more background services are unavailable' "$TEMP_DIR/deploy-output"
rg -q 'The previous stable release is running again' "$TEMP_DIR/deploy-output"
rg -q 'compose up --no-build --pull never --detach --force-recreate app web queue scheduler' "$FAKE_DOCKER_LOG"
rg -q 'compose ps --status running -q queue' "$FAKE_DOCKER_LOG"
rg -q 'compose ps --status running -q scheduler' "$FAKE_DOCKER_LOG"
unset FAKE_STOPPED_SERVICES FAKE_ENABLE_RECOVERY_STUB
rm -f -- "$FAKE_RECOVERY_STARTED"

: > "$FAKE_DOCKER_LOG"
"$PROJECT_ROOT/scripts/rollback.sh"
rg -q 'tag sha256:old-app barber-crm-app:current' "$FAKE_DOCKER_LOG"
rg -q 'tag sha256:old-web barber-crm-web:current' "$FAKE_DOCKER_LOG"
rg -q 'compose up --no-build --pull never --detach --force-recreate app web queue scheduler' "$FAKE_DOCKER_LOG"
rg -q 'compose stop queue scheduler' "$FAKE_DOCKER_LOG"
rg -q 'compose ps --status running -q queue' "$FAKE_DOCKER_LOG"
rg -q 'compose ps --status running -q scheduler' "$FAKE_DOCKER_LOG"
if rg -q 'compose (exec|run).*postgres|migrate:rollback|down --volumes|volume rm' "$FAKE_DOCKER_LOG"; then
    printf 'Rollback unexpectedly touched PostgreSQL, migrations, or volumes.\n' >&2
    exit 1
fi

# A rollback must fail clearly if web is healthy but a background service did
# not remain running after Compose recreated the release.
: > "$FAKE_DOCKER_LOG"
export FAKE_STOPPED_SERVICES=queue
if "$PROJECT_ROOT/scripts/rollback.sh" >"$TEMP_DIR/rollback-output" 2>&1; then
    printf 'Rollback test expected a stopped queue worker to be reported.\n' >&2
    exit 1
fi
rg -q 'Rollback failed: the queue service is not running' "$TEMP_DIR/rollback-output"
rg -q 'compose logs --tail=100 app web queue scheduler' "$FAKE_DOCKER_LOG"
unset FAKE_STOPPED_SERVICES

: > "$FAKE_DOCKER_LOG"
export FAKE_COMPOSE_UP_FAIL=1
if "$PROJECT_ROOT/scripts/rollback.sh" >"$TEMP_DIR/rollback-output" 2>&1; then
    printf 'Rollback test expected Compose startup failure to be reported.\n' >&2
    exit 1
fi
rg -q 'Rollback failed: the application, web, queue, or scheduler service could not be started' "$TEMP_DIR/rollback-output"
rg -q 'compose logs --tail=100 app web queue scheduler' "$FAKE_DOCKER_LOG"
unset FAKE_COMPOSE_UP_FAIL

: > "$FAKE_DOCKER_LOG"
: > "$FAKE_HEALTH_COUNT"
printf 'unhealthy\nhealthy\n' > "$FAKE_HEALTH_SEQUENCE"
if "$PROJECT_ROOT/scripts/deploy.sh"; then
    printf 'Deployment test expected health failure and recovery.\n' >&2
    exit 1
fi
rg -q 'tag sha256:old-app barber-crm-app:current' "$FAKE_DOCKER_LOG"
rg -q 'tag sha256:old-web barber-crm-web:current' "$FAKE_DOCKER_LOG"

# Without a saved previous release, rollback should stop before changing any
# containers or image tags.
cp "$TEMP_DIR/bin/docker" "$TEMP_DIR/bin/docker-with-stable-release"
cat > "$TEMP_DIR/bin/docker" <<'NO_STABLE_DOCKER_STUB'
#!/usr/bin/env bash
set -Eeuo pipefail
printf '%s\n' "$*" >> "$FAKE_DOCKER_LOG"
if [[ "$1" == image && "$2" == inspect ]]; then exit 1; fi
printf 'Unexpected fake docker invocation: %s\n' "$*" >&2
exit 1
NO_STABLE_DOCKER_STUB
chmod 700 "$TEMP_DIR/bin/docker"
: > "$FAKE_DOCKER_LOG"
if "$PROJECT_ROOT/scripts/rollback.sh"; then
    printf 'Rollback without a previous stable release should fail.\n' >&2
    exit 1
fi
if rg -q 'compose stop|compose up|tag ' "$FAKE_DOCKER_LOG"; then
    printf 'Rollback changed deployment state without a previous stable release.\n' >&2
    exit 1
fi

# A failure to switch either image must be detected explicitly.
cp "$TEMP_DIR/bin/docker-with-stable-release" "$TEMP_DIR/bin/docker"
chmod 700 "$TEMP_DIR/bin/docker"
export FAKE_FAIL_TAG_TARGET=barber-crm-web:current
: > "$FAKE_DOCKER_LOG"
if "$PROJECT_ROOT/scripts/rollback.sh" >"$TEMP_DIR/rollback-output" 2>&1; then
    printf 'Rollback test expected an image-tag failure to be reported.\n' >&2
    exit 1
fi
rg -q 'Rollback failed: the saved stable image pair could not be selected' "$TEMP_DIR/rollback-output"
unset FAKE_FAIL_TAG_TARGET

printf 'Deployment and rollback script checks passed.\n'
