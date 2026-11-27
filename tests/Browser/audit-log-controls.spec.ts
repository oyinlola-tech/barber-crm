import { expect, test } from '@playwright/test';
import { createHmac } from 'node:crypto';

function authenticatorCode(secret: string, time = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';

    for (const character of secret) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    const key = Buffer.from(
        bits.match(/.{8}/g)?.map((byte) => parseInt(byte, 2)) ?? [],
    );
    const counter = Math.floor(time / 30_000);
    const message = Buffer.alloc(8);
    message.writeBigUInt64BE(BigInt(counter));
    const digest = createHmac('sha1', key).update(message).digest();
    const offset = digest[digest.length - 1] & 0x0f;
    const value = (digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return value.toString().padStart(6, '0');
}

test('audit export follows the applied filter, not an unsubmitted draft', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.audit-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page.getByLabel('Recovery code').fill('browser-audit-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/operations/audit?action=business.settings_updated');
    const exportLink = page.getByRole('link', { name: 'Export CSV' });
    await expect(exportLink).toHaveAttribute(
        'href',
        '/operations/audit/export?action=business.settings_updated',
    );

    await page.getByLabel('Filter by exact action').fill('staff.deleted');
    await expect(exportLink).toHaveAttribute(
        'href',
        '/operations/audit/export?action=business.settings_updated',
    );
    await page.getByRole('button', { name: 'Filter' }).click();
    await expect(page).toHaveURL(/action=staff.deleted/);
    await expect(exportLink).toHaveAttribute(
        'href',
        '/operations/audit/export?action=staff.deleted',
    );
});
