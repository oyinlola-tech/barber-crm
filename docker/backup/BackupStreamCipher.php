<?php

declare(strict_types=1);

final class BackupStreamCipher
{
    private const MAGIC = 'BARBERSHOP-CRM-BACKUP-V1';

    private const CHUNK_SIZE = 1_048_576;

    public static function encrypt(string $inputPath, string $outputPath, string $passphrase): void
    {
        [$input, $closeInput] = self::openInput($inputPath);
        [$output, $closeOutput] = self::openOutput($outputPath);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = self::deriveKey($passphrase, $salt);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);

        try {
            self::writeAll($output, self::MAGIC.$salt.$header);

            do {
                $plaintext = fread($input, self::CHUNK_SIZE);
                if ($plaintext === false) {
                    throw new RuntimeException('Could not read backup input.');
                }

                $final = feof($input);
                $tag = $final
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $ciphertext = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plaintext, '', $tag);
                self::writeAll($output, pack('N', strlen($ciphertext)).$ciphertext);
            } while (! $final);
        } finally {
            sodium_memzero($key);
            if ($closeInput) {
                fclose($input);
            }
            if ($closeOutput) {
                fclose($output);
            }
        }
    }

    public static function decrypt(string $inputPath, string $outputPath, string $passphrase): void
    {
        [$input, $closeInput] = self::openInput($inputPath);
        [$output, $closeOutput] = self::openOutput($outputPath);
        $key = null;
        try {
            $headerLength = strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
            $prefix = self::readExact($input, $headerLength);

            if (substr($prefix, 0, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new RuntimeException('Backup format is invalid.');
            }

            $saltOffset = strlen(self::MAGIC);
            $salt = substr($prefix, $saltOffset, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $streamHeader = substr($prefix, $saltOffset + SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $key = self::deriveKey($passphrase, $salt);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($streamHeader, $key);
            $finalSeen = false;

            while (! $finalSeen) {
                $lengthBytes = fread($input, 4);
                if ($lengthBytes === false || strlen($lengthBytes) !== 4) {
                    throw new RuntimeException('Backup is truncated before its authenticated end marker.');
                }

                $length = unpack('Nlength', $lengthBytes)['length'];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES
                    || $length > self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new RuntimeException('Backup contains an invalid encrypted chunk length.');
                }

                $ciphertext = self::readExact($input, $length);
                $decrypted = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $ciphertext);
                if ($decrypted === false) {
                    throw new RuntimeException('Backup authentication failed.');
                }

                [$plaintext, $tag] = $decrypted;
                self::writeAll($output, $plaintext);

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $finalSeen = true;
                } elseif ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) {
                    throw new RuntimeException('Backup contains an unsupported encrypted chunk tag.');
                }
            }

            if (fread($input, 1) !== '') {
                throw new RuntimeException('Backup contains data after its authenticated end marker.');
            }
        } finally {
            if ($key !== null) {
                sodium_memzero($key);
            }
            if ($closeInput) {
                fclose($input);
            }
            if ($closeOutput) {
                fclose($output);
            }
        }
    }

    /** @return array{resource, bool} */
    private static function openInput(string $path): array
    {
        if ($path === '-') {
            return [STDIN, false];
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open backup input.');
        }

        return [$stream, true];
    }

    /** @return array{resource, bool} */
    private static function openOutput(string $path): array
    {
        if ($path === '-') {
            return [STDOUT, false];
        }

        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('Could not open backup output.');
        }

        return [$stream, true];
    }

    private static function deriveKey(string $passphrase, string $salt): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    /** @param resource $stream */
    private static function readExact($stream, int $length): string
    {
        $value = '';
        while (strlen($value) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($value));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $value .= $chunk;
        }

        if (strlen($value) !== $length) {
            throw new RuntimeException('Backup is truncated.');
        }

        return $value;
    }

    /** @param resource $stream */
    private static function writeAll($stream, string $value): void
    {
        $offset = 0;
        while ($offset < strlen($value)) {
            $written = fwrite($stream, substr($value, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write backup data.');
            }
            $offset += $written;
        }
    }
}
