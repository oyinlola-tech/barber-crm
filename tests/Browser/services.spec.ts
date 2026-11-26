import { expect, test } from '@playwright/test';

test('authorized staff can manage catalog services from a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.browser@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/services');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Service catalog' }),
    ).toBeVisible();
    await page.getByText('Open navigation').click();
    await expect(page.getByRole('link', { name: 'Services' })).toBeVisible();

    await page.getByLabel('Service name').fill('Classic haircut');
    await page.getByLabel('Duration in minutes').fill('35');
    await page.getByLabel('Price (NGN)').fill('4500.50');
    await page.getByLabel('Description (optional)').fill('Cut and finish.');
    await page.getByRole('button', { name: 'Add to catalog' }).click();

    await expect(
        page.getByRole('heading', { name: 'Classic haircut' }),
    ).toBeVisible();
    await expect(page.getByText('35 minutes')).toBeVisible();
    await expect(page.getByText('Cut and finish.')).toBeVisible();
    await expect(page.getByText(/4,500\.50/)).toBeVisible();

    await page.getByRole('button', { name: 'Edit Classic haircut' }).click();
    const firstEditor = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Save changes' }),
    });
    await firstEditor.getByLabel('Service name').fill('Haircut and beard trim');
    await firstEditor.getByLabel('Duration in minutes').fill('45');
    await firstEditor.getByLabel('Price (NGN)').fill('7500.25');
    await firstEditor.getByLabel('Available for visit records').uncheck();
    await firstEditor.getByRole('button', { name: 'Save changes' }).click();
    await expect(
        page.getByRole('heading', { name: 'Haircut and beard trim' }),
    ).toBeVisible();
    await expect(page.getByText('Inactive', { exact: true })).toBeVisible();

    await page
        .getByRole('button', { name: 'Edit Haircut and beard trim' })
        .click();
    const updatedEditor = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Save changes' }),
    });
    await updatedEditor.getByLabel('Available for visit records').check();
    await updatedEditor.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Active', { exact: true })).toBeVisible();

    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
