import { createHmac } from 'node:crypto';

export function currentTotp(secret: string, timestamp = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    const bytes: number[] = [];
    let buffer = 0;
    let bits = 0;

    for (const character of secret.toUpperCase().replaceAll('=', '')) {
        const value = alphabet.indexOf(character);
        if (value < 0) {
            throw new Error('The TOTP secret is not valid Base32.');
        }

        buffer = (buffer << 5) | value;
        bits += 5;

        if (bits >= 8) {
            bits -= 8;
            bytes.push((buffer >> bits) & 0xff);
            buffer &= (1 << bits) - 1;
        }
    }

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(timestamp / 30_000)));
    const digest = createHmac('sha1', Buffer.from(bytes))
        .update(counter)
        .digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const binary =
        ((digest[offset] & 0x7f) << 24) |
        (digest[offset + 1] << 16) |
        (digest[offset + 2] << 8) |
        digest[offset + 3];

    return String(binary % 1_000_000).padStart(6, '0');
}
