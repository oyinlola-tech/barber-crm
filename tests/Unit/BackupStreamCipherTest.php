<?php

use Symfony\Component\Process\Process;

it('round trips authenticated streaming backups and rejects tampered ciphertext', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $passphrase = base64_encode(random_bytes(48));
    $plaintext = random_bytes(2_097_173);
    $environment = ['BACKUP_ENCRYPTION_PASSPHRASE' => $passphrase];

    $encrypt = new Process([
        PHP_BINARY,
        $projectRoot.'/docker/backup/encrypt.php',
        '-',
        '-',
    ], $projectRoot, $environment);
    $encrypt->setInput($plaintext);
    $encrypt->mustRun();
    $ciphertext = $encrypt->getOutput();

    $decrypt = new Process([
        PHP_BINARY,
        $projectRoot.'/docker/backup/decrypt.php',
        '-',
        '-',
    ], $projectRoot, $environment);
    $decrypt->setInput($ciphertext);
    $decrypt->mustRun();

    expect($decrypt->getOutput())->toBe($plaintext);

    $tamperedOffset = strlen('BARBERSHOP-CRM-BACKUP-V1') + SODIUM_CRYPTO_PWHASH_SALTBYTES
        + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES + 4 + 8;
    $tampered = $ciphertext;
    $tampered[$tamperedOffset] = chr(ord($tampered[$tamperedOffset]) ^ 1);
    $tamperedPath = tempnam(sys_get_temp_dir(), 'crm-backup-tampered-');
    if ($tamperedPath === false) {
        throw new RuntimeException('Could not allocate a temporary encrypted backup.');
    }
    file_put_contents($tamperedPath, $tampered);
    try {
        $rejected = new Process([
            PHP_BINARY,
            $projectRoot.'/docker/backup/decrypt.php',
            $tamperedPath,
            '-',
        ], $projectRoot, $environment);
        $rejected->run();

        expect($rejected->isSuccessful())->toBeFalse()
            ->and($rejected->getErrorOutput())->toContain('authentication failed');
    } finally {
        unlink($tamperedPath);
    }
});
