import { expect, test } from '@playwright/test';

test('owner can revoke another session without revoking the current browser', async ({
    browser,
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.sessions-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-sessions-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    const otherContext = await browser.newContext();
    const otherPage = await otherContext.newPage();
    await otherPage.goto('/login');
    await otherPage
        .getByLabel('Email address')
        .fill('crm.sessions-owner@example.test');
    await otherPage
        .getByLabel('Password')
        .fill('Browser-only owner password 9842!');
    await otherPage.getByRole('button', { name: 'Sign in' }).click();
    await expect(otherPage).toHaveURL(/two-factor-challenge/);
    await otherPage
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await otherPage
        .getByLabel('Recovery code')
        .fill('browser-sessions-second-recovery-code');
    await otherPage.getByRole('button', { name: 'Continue' }).click();
    await expect(otherPage).toHaveURL(/dashboard/);
    await otherContext.close();

    await page.goto('/settings/security');
    await expect(
        page.getByRole('heading', { name: 'Active sessions' }),
    ).toBeVisible();
    await expect(page.getByText('This browser', { exact: true })).toBeVisible();
    const otherSession = page
        .locator('li')
        .filter({ hasText: 'Browser session' })
        .filter({ has: page.getByRole('button', { name: 'Revoke' }) })
        .first();
    await expect(otherSession).toBeVisible();
    await otherSession.getByRole('button', { name: 'Revoke' }).click();

    await expect(
        page.getByRole('button', { name: 'Revoke', exact: true }),
    ).toHaveCount(0);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/dashboard/);
});
