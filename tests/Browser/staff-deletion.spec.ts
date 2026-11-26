import { expect, test } from '@playwright/test';

test('owner can revoke staff access and redact personal details on mobile', async ({
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
    await page.getByLabel('Recovery code').fill('browser-staff-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/staff');
    const barber = page
        .locator('article')
        .filter({ hasText: 'Browser Test Barber' });
    await expect(barber).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await barber.getByRole('button', { name: 'Remove access' }).click();
    const removedStaff = page
        .locator('article')
        .filter({ hasText: 'Deleted staff member' });
    await expect(removedStaff).toBeVisible();
    await expect(
        removedStaff.getByText('Personal details redacted'),
    ).toBeVisible();
    await expect(
        removedStaff.getByRole('button', { name: 'Remove access' }),
    ).toHaveCount(0);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
