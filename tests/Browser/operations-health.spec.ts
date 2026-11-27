import { expect, test } from '@playwright/test';

test('owner health page shows unresolved WhatsApp callback count', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.owner@example.test');
    await page
        .getByLabel('Password')
        .fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page.getByLabel('Recovery code').fill('browser-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/operations/health');
    await expect(
        page.getByText(/0 pending · 1 stale callbacks · 0 failed in 24 hours/),
    ).toBeVisible();
    await expect(
        page.getByText('degraded', { exact: true }).first(),
    ).toBeVisible();
});
