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

previous_stable_release_available() {
    docker image inspect barber-crm-app:previous-stable >/dev/null 2>&1 \
        && docker image inspect barber-crm-web:previous-stable >/dev/null 2>&1
}

snapshot_running_release_as_previous() {
    local app_container web_container app_image web_image

    app_container="$(docker compose ps --status running -q app)" || return 1
    web_container="$(docker compose ps --status running -q web)" || return 1

    # A first deployment has no running pair to preserve. Existing saved
    # rollback tags, if any, remain available in that case.
    if [[ -z "$app_container" && -z "$web_container" ]]; then
        return 2
    fi
    [[ -n "$app_container" && -n "$web_container" ]] || return 1

    app_image="$(docker inspect --format '{{.Image}}' "$app_container")" || return 1
    web_image="$(docker inspect --format '{{.Image}}' "$web_container")" || return 1
    [[ -n "$app_image" && -n "$web_image" ]] || return 1

    tag_image_pair_as_previous "$app_image" "$web_image"
}

tag_image_pair_as_previous() {
    local app_image="$1"
    local web_image="$2"
    local previous_app_image previous_web_image

    previous_app_image="$(docker image inspect --format '{{.Id}}' barber-crm-app:previous-stable 2>/dev/null || true)"
    previous_web_image="$(docker image inspect --format '{{.Id}}' barber-crm-web:previous-stable 2>/dev/null || true)"

    docker tag "$app_image" barber-crm-app:previous-stable || return 1
    if ! docker tag "$web_image" barber-crm-web:previous-stable; then
        if [[ -n "$previous_app_image" ]]; then
            docker tag "$previous_app_image" barber-crm-app:previous-stable || true
        else
            docker image rm barber-crm-app:previous-stable >/dev/null 2>&1 || true
        fi
        if [[ -n "$previous_web_image" ]]; then
            docker tag "$previous_web_image" barber-crm-web:previous-stable || true
        else
            docker image rm barber-crm-web:previous-stable >/dev/null 2>&1 || true
        fi
        return 1
    fi
}

restore_previous_release_tags() {
    local app_image web_image current_app_image current_web_image

    app_image="$(docker image inspect --format '{{.Id}}' barber-crm-app:previous-stable)" || return 1
    web_image="$(docker image inspect --format '{{.Id}}' barber-crm-web:previous-stable)" || return 1
    current_app_image="$(docker image inspect --format '{{.Id}}' barber-crm-app:current 2>/dev/null || true)"
    current_web_image="$(docker image inspect --format '{{.Id}}' barber-crm-web:current 2>/dev/null || true)"
    [[ -n "$app_image" && -n "$web_image" ]] || return 1

    docker tag "$app_image" barber-crm-app:current || return 1
    if ! docker tag "$web_image" barber-crm-web:current; then
        if [[ -n "$current_app_image" ]]; then
            docker tag "$current_app_image" barber-crm-app:current || true
        else
            docker image rm barber-crm-app:current >/dev/null 2>&1 || true
        fi
        if [[ -n "$current_web_image" ]]; then
            docker tag "$current_web_image" barber-crm-web:current || true
        fi
        return 1
    fi
}

start_current_release() {
    docker compose up --no-build --pull never --detach --force-recreate app web queue scheduler
}
