#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/../.." && pwd)"
cd "$PROJECT_ROOT"
COMPOSE_FILE_PATH="${BARBER_CRM_COMPOSE_FILE:-$PROJECT_ROOT/compose.yaml}"
COMPOSE_ENV_PATH="${BARBER_CRM_COMPOSE_ENV_FILE:-$PROJECT_ROOT/.env}"
COMPOSE=(docker compose --env-file "$COMPOSE_ENV_PATH" -f "$COMPOSE_FILE_PATH")

if [[ ! "${BACKUP_ENCRYPTION_PASSPHRASE:-}" =~ ^[A-Za-z0-9_+=/-]{32,}$ ]]; then
    printf 'Set BACKUP_ENCRYPTION_PASSPHRASE to a generated 32-character minimum base64-style secret.\n' >&2
    exit 2
fi

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_ROOT/storage/backups}"
mkdir -p -- "$BACKUP_DIR"
chmod 700 -- "$BACKUP_DIR"

SECRET_ENV_FILE="$(mktemp "${TMPDIR:-/tmp}/barber-crm-backup-env.XXXXXX")"
COMPOSE_OVERRIDE="$(mktemp "${TMPDIR:-/tmp}/barber-crm-backup-compose.XXXXXX")"
ENCRYPTED_TEMP="$(mktemp "$BACKUP_DIR/.barber-crm-backup.XXXXXX")"
cleanup() {
    rm -f -- "$SECRET_ENV_FILE" "$COMPOSE_OVERRIDE" "$ENCRYPTED_TEMP"
}
trap cleanup EXIT
chmod 600 -- "$SECRET_ENV_FILE" "$COMPOSE_OVERRIDE" "$ENCRYPTED_TEMP"
printf 'BACKUP_ENCRYPTION_PASSPHRASE=%s\n' "$BACKUP_ENCRYPTION_PASSPHRASE" > "$SECRET_ENV_FILE"
printf 'services:\n  app:\n    env_file:\n      - "%s"\n' "$SECRET_ENV_FILE" > "$COMPOSE_OVERRIDE"

BACKUP_NAME="barbershop-crm-$(date -u +%Y%m%dT%H%M%SZ).dump.enc"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
if [[ -e "$BACKUP_PATH" ]]; then
    printf 'A backup with the target timestamp already exists.\n' >&2
    exit 1
fi

"${COMPOSE[@]}" exec -T postgres \
    sh -ec 'exec pg_dump --format=custom -U "$POSTGRES_USER" -d "$POSTGRES_DB"' |
    "${COMPOSE[@]}" -f "$COMPOSE_OVERRIDE" \
        run --rm --no-deps -T app \
        php /var/www/html/docker/backup/encrypt.php - - > "$ENCRYPTED_TEMP"

if [[ ! -s "$ENCRYPTED_TEMP" ]]; then
    printf 'The encrypted backup output is empty.\n' >&2
    exit 1
fi

mv -- "$ENCRYPTED_TEMP" "$BACKUP_PATH"
chmod 600 -- "$BACKUP_PATH"

if [[ -n "${BACKUP_SCP_TARGET:-}" ]]; then
    : "${BACKUP_SSH_KNOWN_HOSTS:?Set BACKUP_SSH_KNOWN_HOSTS to a verified known_hosts file.}"
    scp -o BatchMode=yes -o StrictHostKeyChecking=yes \
        -o "UserKnownHostsFile=$BACKUP_SSH_KNOWN_HOSTS" \
        -- "$BACKUP_PATH" "${BACKUP_SCP_TARGET%/}/$BACKUP_NAME"
fi

printf 'Encrypted database backup created: %s\n' "$BACKUP_PATH"
