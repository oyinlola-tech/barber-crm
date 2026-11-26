#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_ROOT"
COMPOSE_FILE_PATH="${BARBER_CRM_COMPOSE_FILE:-$PROJECT_ROOT/compose.yaml}"
COMPOSE_ENV_PATH="${BARBER_CRM_COMPOSE_ENV_FILE:-$PROJECT_ROOT/.env}"
COMPOSE=(docker compose --env-file "$COMPOSE_ENV_PATH" -f "$COMPOSE_FILE_PATH")

if [[ $# -ne 1 || ! -f "$1" ]]; then
    printf 'Usage: RESTORE_DATABASE=barber_crm_restore_<name> BACKUP_ENCRYPTION_PASSPHRASE=<secret> %s <backup.dump.enc>\n' "$0" >&2
    exit 2
fi
if [[ ! "${BACKUP_ENCRYPTION_PASSPHRASE:-}" =~ ^[A-Za-z0-9_+=/-]{32,}$ ]]; then
    printf 'Set the original 32-character minimum base64-style backup secret.\n' >&2
    exit 2
fi
if [[ ! "${RESTORE_DATABASE:-}" =~ ^barber_crm_restore_[a-z0-9_]+$ ]]; then
    printf 'RESTORE_DATABASE must use the reserved barber_crm_restore_<name> prefix.\n' >&2
    exit 2
fi

BACKUP_PATH="$(realpath -- "$1")"
SECRET_ENV_FILE="$(mktemp "${TMPDIR:-/tmp}/barber-crm-restore-env.XXXXXX")"
COMPOSE_OVERRIDE="$(mktemp "${TMPDIR:-/tmp}/barber-crm-restore-compose.XXXXXX")"
RESTORE_OK=0
DATABASE_CREATED=0
cleanup() {
    if [[ "$DATABASE_CREATED" == 1 && "$RESTORE_OK" != 1 ]]; then
        "${COMPOSE[@]}" exec -T postgres \
            sh -ec 'exec dropdb -U "$POSTGRES_USER" --if-exists "$1"' sh "$RESTORE_DATABASE" \
            >/dev/null 2>&1 || true
    fi
    rm -f -- "$SECRET_ENV_FILE" "$COMPOSE_OVERRIDE"
}
trap cleanup EXIT
chmod 600 -- "$SECRET_ENV_FILE" "$COMPOSE_OVERRIDE"
printf 'BACKUP_ENCRYPTION_PASSPHRASE=%s\n' "$BACKUP_ENCRYPTION_PASSPHRASE" > "$SECRET_ENV_FILE"
printf 'services:\n  app:\n    env_file:\n      - "%s"\n' "$SECRET_ENV_FILE" > "$COMPOSE_OVERRIDE"

decrypt_stream() {
    "${COMPOSE[@]}" -f "$COMPOSE_OVERRIDE" \
        run --rm --no-deps -T app \
        php /var/www/html/docker/backup/decrypt.php - -
}

if [[ "$("${COMPOSE[@]}" exec -T postgres \
    sh -ec 'exec psql -U "$POSTGRES_USER" -d postgres -Atc "$1"' sh \
    "SELECT 1 FROM pg_database WHERE datname = '$RESTORE_DATABASE'")" == 1 ]]; then
    printf 'Restore target database already exists; refusing to modify it.\n' >&2
    exit 1
fi

if ! decrypt_stream < "$BACKUP_PATH" |
    "${COMPOSE[@]}" exec -T postgres pg_restore --list >/dev/null; then
    printf 'Backup decryption, authentication, or archive validation failed.\n' >&2
    exit 1
fi

"${COMPOSE[@]}" exec -T postgres \
    sh -ec 'exec createdb -U "$POSTGRES_USER" --template=template0 "$1"' sh "$RESTORE_DATABASE"
DATABASE_CREATED=1

decrypt_stream < "$BACKUP_PATH" |
    "${COMPOSE[@]}" exec -T postgres \
        sh -ec 'exec pg_restore --exit-on-error --no-owner --no-privileges -U "$POSTGRES_USER" -d "$1"' \
        sh "$RESTORE_DATABASE"

RESTORE_OK=1
printf 'Backup restored to new database %s. The configured application database was not changed.\n' "$RESTORE_DATABASE"
