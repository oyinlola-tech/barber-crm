#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/barber-crm-deploy-test.XXXXXX")"
trap 'rm -rf -- "$TEMP_DIR"' EXIT
mkdir -p "$TEMP_DIR/bin"
export FAKE_DOCKER_LOG="$TEMP_DIR/docker.log"
export FAKE_HEALTH_SEQUENCE="$TEMP_DIR/health-sequence"
export FAKE_HEALTH_COUNT="$TEMP_DIR/health-count"

cat > "$TEMP_DIR/bin/docker" <<'DOCKER_STUB'
#!/usr/bin/env bash
set -Eeuo pipefail
printf '%s\n' "$*" >> "$FAKE_DOCKER_LOG"

if [[ "$1" == compose ]]; then
    shift
    if [[ "$1" == up && "${FAKE_COMPOSE_UP_FAIL:-0}" == 1 ]]; then
        exit 1
    fi
    if [[ "$1" == ps ]]; then
        service="${*: -1}"
        if [[ " ${*} " == *" --status running "* \
            && " ${FAKE_STOPPED_SERVICES:-} " == *" ${service} "* ]]; then
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
    exit 0
fi

if [[ "$1" == tag ]]; then
    exit 0
fi

printf 'Unexpected fake docker invocation: %s\n' "$*" >&2
exit 1
DOCKER_STUB
chmod 700 "$TEMP_DIR/bin/docker"
export PATH="$TEMP_DIR/bin:$PATH"

: > "$FAKE_DOCKER_LOG"
"$PROJECT_ROOT/scripts/deploy.sh"
rg -q 'tag sha256:old-app barber-crm-app:previous-stable' "$FAKE_DOCKER_LOG"
rg -q 'tag sha256:old-web barber-crm-web:previous-stable' "$FAKE_DOCKER_LOG"
rg -q 'compose up --build --detach app web queue scheduler' "$FAKE_DOCKER_LOG"

: > "$FAKE_DOCKER_LOG"
"$PROJECT_ROOT/scripts/rollback.sh"
rg -q 'tag barber-crm-app:previous-stable barber-crm-app:current' "$FAKE_DOCKER_LOG"
rg -q 'tag barber-crm-web:previous-stable barber-crm-web:current' "$FAKE_DOCKER_LOG"
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
rg -q 'tag barber-crm-app:previous-stable barber-crm-app:current' "$FAKE_DOCKER_LOG"
rg -q 'tag barber-crm-web:previous-stable barber-crm-web:current' "$FAKE_DOCKER_LOG"

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

printf 'Deployment and rollback script checks passed.\n'
