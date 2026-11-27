#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

PROJECT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
DRILL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/barber-crm-backup-drill.XXXXXX")"
DRILL_PROJECT="barber-crm-backup-drill-$$"
DRILL_DATABASE='barber_crm_backup_drill'
DRILL_USER='crm_backup_drill'
DRILL_PASSWORD="$(openssl rand -hex 24)"
DRILL_PASSPHRASE="$(openssl rand -base64 48 | tr -d '\n')"
DRILL_RESTORE_DATABASE="barber_crm_restore_drill_$(date -u +%Y%m%d%H%M%S)"
DRILL_INVALID_DATABASE="barber_crm_restore_invalid_$(date -u +%Y%m%d%H%M%S)"
DRILL_FAILURE_DATABASE="barber_crm_restore_fail_$(date -u +%Y%m%d%H%M%S)"

export COMPOSE_PROJECT_NAME="$DRILL_PROJECT"
export BARBER_CRM_COMPOSE_FILE="$DRILL_DIR/compose.yaml"
export BARBER_CRM_COMPOSE_ENV_FILE="$DRILL_DIR/compose.env"
export BACKUP_ENCRYPTION_PASSPHRASE="$DRILL_PASSPHRASE"
export BACKUP_DIR="$DRILL_DIR/backups"
export DRILL_BACKUP_SOURCE="$PROJECT_ROOT/docker/backup"
export DRILL_SHARED_DIR="$DRILL_DIR"

cleanup() {
    docker compose --env-file "$BARBER_CRM_COMPOSE_ENV_FILE" \
        -f "$BARBER_CRM_COMPOSE_FILE" down --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$DRILL_DIR"
}
trap cleanup EXIT

if ! docker image inspect barber-crm-app:latest >/dev/null 2>&1; then
    printf 'Build barber-crm-app:latest before running this isolated recovery drill.\n' >&2
    exit 2
fi

cat > "$BARBER_CRM_COMPOSE_ENV_FILE" <<EOF
POSTGRES_DB=$DRILL_DATABASE
POSTGRES_USER=$DRILL_USER
POSTGRES_PASSWORD=$DRILL_PASSWORD
EOF
mkdir -p -- "$DRILL_DIR/fake-bin"

cat > "$BARBER_CRM_COMPOSE_FILE" <<'EOF'
services:
  postgres:
    image: postgres:17-alpine
    environment:
      POSTGRES_DB: ${POSTGRES_DB:?}
      POSTGRES_USER: ${POSTGRES_USER:?}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?}
      PATH: /drill-bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 2s
      timeout: 2s
      retries: 20
    volumes:
      - ${DRILL_SHARED_DIR:?}/fake-bin:/drill-bin:ro
  app:
    image: barber-crm-app:latest
    volumes:
      - ${DRILL_BACKUP_SOURCE:?}:/var/www/html/docker/backup:ro
      - ${DRILL_SHARED_DIR:?}:/drill:rw
EOF

cat > "$DRILL_DIR/fake-bin/pg_restore" <<'EOF'
#!/bin/sh
case "$*" in
    *--list*) exec /usr/local/bin/pg_restore "$@" ;;
    *barber_crm_restore_fail_*) exit 42 ;;
    *) exec /usr/local/bin/pg_restore "$@" ;;
esac
EOF
chmod 755 -- "$DRILL_DIR/fake-bin/pg_restore"

compose() {
    docker compose --env-file "$BARBER_CRM_COMPOSE_ENV_FILE" \
        -f "$BARBER_CRM_COMPOSE_FILE" "$@"
}

compose up -d postgres >/dev/null
ready=0
for attempt in $(seq 1 40); do
    if compose exec -T postgres psql -v ON_ERROR_STOP=1 -U "$DRILL_USER" -d "$DRILL_DATABASE" -Atc 'SELECT 1' >/dev/null 2>&1; then
        ready=1
        break
    fi
    sleep 1
done
if (( ready != 1 )); then
    printf 'Disposable PostgreSQL did not become ready.\n' >&2
    exit 1
fi

compose exec -T postgres psql -v ON_ERROR_STOP=1 -U "$DRILL_USER" -d "$DRILL_DATABASE" \
    -c "CREATE TABLE recovery_probe (id integer PRIMARY KEY, marker text NOT NULL); INSERT INTO recovery_probe VALUES (7, 'barber-crm-recovery-ok');" \
    >/dev/null

backup_output="$("$PROJECT_ROOT/docker/backup/backup.sh")"
backup_path="${backup_output#*created: }"
if [[ ! -s "$backup_path" || "$(stat -c '%a' "$backup_path")" != 600 ]]; then
    printf 'Backup artifact is missing, empty, or has unsafe permissions.\n' >&2
    exit 1
fi
if [[ "$(dd if="$backup_path" bs=1 count=24 status=none)" != 'BARBERSHOP-CRM-BACKUP-V1' ]]; then
    printf 'Backup artifact does not have the expected encrypted format marker.\n' >&2
    exit 1
fi

RESTORE_DATABASE="$DRILL_RESTORE_DATABASE" \
    "$PROJECT_ROOT/docker/backup/restore-to-new-database.sh" "$backup_path"
restored_marker="$(compose exec -T postgres psql -U "$DRILL_USER" -d "$DRILL_RESTORE_DATABASE" \
    -Atc 'SELECT marker FROM recovery_probe WHERE id=7')"
if [[ "$restored_marker" != 'barber-crm-recovery-ok' ]]; then
    printf 'The restored database did not contain the expected recovery probe.\n' >&2
    exit 1
fi

if RESTORE_DATABASE="$DRILL_RESTORE_DATABASE" \
    "$PROJECT_ROOT/docker/backup/restore-to-new-database.sh" "$backup_path" \
    >"$DRILL_DIR/existing-target.log" 2>&1; then
    printf 'Restore unexpectedly accepted an existing database.\n' >&2
    exit 1
fi
grep -Eq 'already exists; refusing to modify it' "$DRILL_DIR/existing-target.log"

cp -- "$backup_path" "$DRILL_DIR/tampered.dump.enc"
printf '\001' | dd of="$DRILL_DIR/tampered.dump.enc" bs=1 seek=80 conv=notrunc status=none
if RESTORE_DATABASE="$DRILL_INVALID_DATABASE" \
    "$PROJECT_ROOT/docker/backup/restore-to-new-database.sh" "$DRILL_DIR/tampered.dump.enc" \
    >"$DRILL_DIR/tampered-backup.log" 2>&1; then
    printf 'Restore unexpectedly accepted a tampered backup.\n' >&2
    exit 1
fi
grep -Eq 'Backup decryption, authentication, or archive validation failed' "$DRILL_DIR/tampered-backup.log"
invalid_database="$(compose exec -T postgres psql -U "$DRILL_USER" -d postgres -Atc \
    "SELECT 1 FROM pg_database WHERE datname='$DRILL_INVALID_DATABASE'")"
if [[ -n "$invalid_database" ]]; then
    printf 'A database was created for a tampered backup.\n' >&2
    exit 1
fi

if RESTORE_DATABASE="$DRILL_FAILURE_DATABASE" \
    "$PROJECT_ROOT/docker/backup/restore-to-new-database.sh" "$backup_path" \
    >"$DRILL_DIR/restore-failure.log" 2>&1; then
    printf 'Restore unexpectedly succeeded during the forced post-creation failure case.\n' >&2
    exit 1
fi
failure_database="$(compose exec -T postgres psql -U "$DRILL_USER" -d postgres -Atc \
    "SELECT 1 FROM pg_database WHERE datname='$DRILL_FAILURE_DATABASE'")"
if [[ -n "$failure_database" ]]; then
    printf 'A failed restore left its newly created database behind.\n' >&2
    exit 1
fi
original_marker="$(compose exec -T postgres psql -U "$DRILL_USER" -d "$DRILL_DATABASE" \
    -Atc 'SELECT marker FROM recovery_probe WHERE id=7')"
if [[ "$original_marker" != 'barber-crm-recovery-ok' ]]; then
    printf 'A failed restore changed the source database.\n' >&2
    exit 1
fi

printf 'Isolated backup/restore drill passed: artifact encryption, restored row, existing-target refusal, tamper rejection before creation, and failed-target cleanup.\n'
