# Encrypted PostgreSQL backups

The repository provides an encrypted custom-format PostgreSQL dump and a
restore command that only creates a new database in the reserved
`barber_crm_restore_<name>` namespace. It will not overwrite the configured
application database.

## Create a backup

Generate a long random secret once and store it in an approved secret manager.
The secret is the encryption key material: losing it makes existing backups
unrecoverable. Do not put it in source control, shell history, logs, or the
application `.env` file.

```sh
openssl rand -base64 48
BACKUP_ENCRYPTION_PASSPHRASE='<secret-from-secret-manager>' \
  docker/backup/backup.sh
```

By default, encrypted artifacts are written with mode `0600` under
`storage/backups`; the directory is mode `0700`. Set `BACKUP_DIR` to a
protected mounted location when required. The plaintext dump is streamed
directly from `pg_dump` through the PHP Sodium XChaCha20-Poly1305 secretstream
implementation and is not written as a host file. Each encrypted chunk is
authenticated, and the decryptor requires a valid authenticated final marker.

To copy the resulting ciphertext to a remote host, configure
`BACKUP_SCP_TARGET` and point `BACKUP_SSH_KNOWN_HOSTS` at a file containing
independently verified host keys. Strict host key checking is enabled. SCP
transport, remote retention, offsite access controls and remote restore remain
operator responsibilities and were not exercised by the isolated local drill.

## Restore to a separate database

Restore requires the original secret. The command authenticates and validates
the archive before creating a database. It refuses an existing restore target;
if the restore fails after creation, it attempts to remove only that newly
created target. A successful restore remains available for inspection and
does not change the application's configured database.

```sh
RESTORE_DATABASE=barber_crm_restore_drill_20261126 \
BACKUP_ENCRYPTION_PASSPHRASE='<secret-from-secret-manager>' \
  docker/backup/restore-to-new-database.sh \
  storage/backups/barbershop-crm-<timestamp>.dump.enc
```

Review the restored database, migration history, row counts, constraints and
application behavior before planning any cutover. Cutover is a separate
controlled deployment operation and is not automated by this script. Never
point a restore target at the live database or relax the reserved namespace
guard.

## Verification and operational limits

The isolated PostgreSQL 17 drill created test data, produced an encrypted
artifact using the application runtime image, decrypted and authenticated it,
restored it to a new database, and verified the restored row. The unit test
also verifies a streaming round trip and rejects tampered ciphertext. These
checks do not prove production backup scheduling, remote copy, retention,
secret recovery, disaster recovery objectives, or restore performance at the
barbershop's eventual database size. Schedule and record periodic restore
drills before production use.

Do not remove historical appointment-related migrations or tables as part of a
restore. Their applied state and business data have not been inspected. Any
schema cleanup requires a reviewed backup and additive migration plan.
