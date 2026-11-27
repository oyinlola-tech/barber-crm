import { expect, test } from '@playwright/test';

test('authorized staff can create, enable, and edit a follow-up rule', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.customer-lifecycle@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/automations');
    await expect(
        page.getByRole('heading', { name: 'Follow-up automations' }),
    ).toBeVisible();
    await page.getByLabel('Rule name').fill('Visit thank-you follow-up');
    await page.getByLabel('Delay (minutes)').fill('10');
    await page
        .getByLabel('Approved template')
        .selectOption({ label: 'browser_return_follow_up (en)' });
    await page.getByRole('button', { name: 'Create automation' }).click();

    const rule = page.locator('article').filter({
        has: page.getByRole('heading', { name: 'Visit thank-you follow-up' }),
    });
    await expect(rule).toContainText('10 minute delay');
    await rule.getByRole('button', { name: 'Enable' }).click();
    await expect(rule.getByRole('button', { name: 'Disable' })).toBeVisible();

    await rule.getByRole('button', { name: 'Edit' }).click();
    await expect(
        page.getByRole('heading', { name: 'Edit automation rule' }),
    ).toBeVisible();
    await page.getByLabel('Rule name').fill('Three-day visit follow-up');
    await page.getByLabel('Delay (minutes)').fill('4320');
    await page.getByRole('button', { name: 'Save changes' }).click();

    const updatedRule = page.locator('article').filter({
        has: page.getByRole('heading', { name: 'Three-day visit follow-up' }),
    });
    await expect(updatedRule).toContainText('4320 minute delay');
    await expect(
        updatedRule.getByRole('button', { name: 'Disable' }),
    ).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
