import { expect, test } from '@playwright/test';

test('owner can delete an unused local template draft on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.template-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-template-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/templates');
    await expect(
        page.getByRole('heading', { name: 'WhatsApp templates' }),
    ).toBeVisible();
    await page.getByLabel('Template name').fill('unused_local_draft');
    await page.getByLabel('Message body').fill('Thanks for visiting.');
    await page.getByRole('button', { name: 'Save draft' }).click();

    const draft = page.locator('article').filter({
        hasText: 'unused_local_draft',
    });
    await expect(draft).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await draft.getByRole('button', { name: 'Delete draft' }).click();
    await expect(
        page.locator('article').filter({
            hasText: 'browser_return_follow_up',
        }),
    ).toBeVisible();
    await expect(draft).toHaveCount(0);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
