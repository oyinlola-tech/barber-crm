#!/usr/bin/env bash

set -Eeuo pipefail

wait_for_web() {
    local timeout_seconds="${DEPLOY_HEALTH_TIMEOUT_SECONDS:-120}"
    local deadline container_id status

    if [[ ! "$timeout_seconds" =~ ^[0-9]+$ ]] || (( timeout_seconds < 1 )); then
        printf 'DEPLOY_HEALTH_TIMEOUT_SECONDS must be a positive integer.\n' >&2
        return 2
    fi
    deadline=$((SECONDS + timeout_seconds))

    while (( SECONDS < deadline )); do
        container_id="$(docker compose ps -q web)"
        if [[ -n "$container_id" ]]; then
            status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}missing{{end}}' "$container_id")"
            if [[ "$status" == healthy ]]; then
                return 0
            fi
            if [[ "$status" == unhealthy || "$status" == missing ]]; then
                break
            fi
        fi
        sleep 2
    done

    docker compose logs --tail=100 web app queue scheduler >&2 || true
    printf 'The web service did not become healthy within %s seconds.\n' "$timeout_seconds" >&2
    return 1
}

verify_background_services() {
    local service

    for service in queue scheduler; do
        if [[ -z "$(docker compose ps --status running -q "$service")" ]]; then
            printf 'The %s service is not running.\n' "$service" >&2
            docker compose logs --tail=100 app web queue scheduler >&2 || true
            return 1
        fi
    done
}

recover_previous_release() {
    local previous_available="$1"

    if (( previous_available == 0 )); then
        printf 'No previous stable release is available for automatic recovery.\n' >&2

        return 1
    fi

    printf 'Restoring the previous stable release after deployment checks failed.\n' >&2
    if ! restore_previous_release_tags || ! start_current_release || ! wait_for_web || ! verify_background_services; then
        docker compose logs --tail=100 app web queue scheduler >&2 || true
        printf 'Automatic recovery could not verify the previous stable release.\n' >&2

        return 1
    fi

    printf 'The previous stable release is running again.\n' >&2

    return 0
}

tag_running_release_as_previous() {
    local app_container web_container app_image web_image

    app_container="$(docker compose ps -q app)"
    web_container="$(docker compose ps -q web)"
    if [[ -n "$app_container" && -n "$web_container" ]]; then
        app_image="$(docker inspect --format '{{.Image}}' "$app_container")"
        web_image="$(docker inspect --format '{{.Image}}' "$web_container")"
    else
        app_image="$(docker image inspect --format '{{.Id}}' barber-crm-app:current 2>/dev/null || true)"
        web_image="$(docker image inspect --format '{{.Id}}' barber-crm-web:current 2>/dev/null || true)"
    fi

    if [[ -z "$app_image" || -z "$web_image" ]]; then
        return 1
    fi

    docker tag "$app_image" barber-crm-app:previous-stable
    docker tag "$web_image" barber-crm-web:previous-stable
    return 0
}

restore_previous_release_tags() {
    docker image inspect barber-crm-app:previous-stable >/dev/null
    docker image inspect barber-crm-web:previous-stable >/dev/null
    docker tag barber-crm-app:previous-stable barber-crm-app:current
    docker tag barber-crm-web:previous-stable barber-crm-web:current
}

start_current_release() {
    docker compose up --no-build --pull never --detach --force-recreate app web queue scheduler
}
