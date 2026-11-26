<?php

declare(strict_types=1);

require_once __DIR__.'/BackupStreamCipher.php';

$passphrase = getenv('BACKUP_ENCRYPTION_PASSPHRASE');
if (! is_string($passphrase) || strlen($passphrase) < 32 || ! extension_loaded('sodium')) {
    fwrite(STDERR, "Set a 32-character minimum BACKUP_ENCRYPTION_PASSPHRASE and enable sodium.\n");
    exit(2);
}

try {
    BackupStreamCipher::encrypt($argv[1] ?? '-', $argv[2] ?? '-', $passphrase);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backup encryption failed: '.$exception->getMessage()."\n");
    exit(1);
} finally {
    sodium_memzero($passphrase);
}
