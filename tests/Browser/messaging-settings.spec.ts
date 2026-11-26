import { expect, test } from '@playwright/test';

test('owner can update WhatsApp opt-out phrases on a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page.getByLabel('Recovery code').fill('browser-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/settings/business');
    await expect(
        page.getByRole('heading', { name: 'WhatsApp messaging rules' }),
    ).toBeVisible();
    const phrases = page.getByLabel('Additional customer opt-out phrases');
    await phrases.fill('NO MORE\nREMOVE ME');
    await page.getByRole('button', { name: 'Save messaging rules' }).click();
    await expect(phrases).toHaveValue('NO MORE\nREMOVE ME');

    await page.reload();
    await expect(phrases).toHaveValue('NO MORE\nREMOVE ME');
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
