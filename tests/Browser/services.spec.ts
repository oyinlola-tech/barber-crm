import { expect, test } from '@playwright/test';

test('authorized staff can manage catalog services from a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.services@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.keyboard.press('Tab');
    const skipLink = page.getByRole('link', {
        name: 'Skip to main content',
    });
    await expect(skipLink).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    await page.goto('/services');
    await expect(
        page.getByRole('heading', { level: 1, name: 'Service catalog' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Open more navigation' }).click();
    await expect(page.getByRole('link', { name: 'Services' })).toBeVisible();

    await page.getByLabel('Category name').fill('Haircuts');
    await page.getByLabel('Sort order').fill('1');
    await page.getByRole('button', { name: 'Add category' }).click();
    const categorySection = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'Service categories' }),
    });
    await expect(
        categorySection.getByRole('heading', { name: 'Haircuts' }),
    ).toBeVisible();

    const serviceForm = page.locator('form').filter({
        has: page.getByRole('button', { name: 'Add to catalog' }),
    });
    await serviceForm.getByLabel('Service name').fill('Classic haircut');
    await serviceForm.getByLabel('Duration in minutes').fill('35');
    await serviceForm.getByLabel('Price (NGN)').fill('4500.50');
    await serviceForm
        .getByLabel('Description (optional)')
        .fill('Cut and finish.');
    await serviceForm
        .getByLabel('Category (optional)')
        .selectOption({ label: 'Haircuts' });
    await serviceForm.getByRole('button', { name: 'Add to catalog' }).click();

    await expect(
        page.getByRole('heading', { name: 'Classic haircut' }),
    ).toBeVisible();
    await expect(page.getByText('35 minutes')).toBeVisible();
    await expect(page.getByText('Cut and finish.')).toBeVisible();
    await expect(page.getByText(/4,500\.50/)).toBeVisible();
    const serviceRow = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Edit Classic haircut' }),
    });
    await expect(
        serviceRow.getByText('Haircuts', { exact: true }),
    ).toBeVisible();

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

    await page.getByRole('button', { name: 'Edit category Haircuts' }).click();
    const categoryEditor = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Save category' }),
    });
    await categoryEditor.getByLabel('Category name').fill('Hair and beard');
    await categoryEditor.getByRole('button', { name: 'Save category' }).click();
    await expect(
        categorySection.getByRole('heading', { name: 'Hair and beard' }),
    ).toBeVisible();

    await page.getByLabel('Category name').fill('Unused category');
    await page.getByRole('button', { name: 'Add category' }).click();
    await expect(
        page.getByRole('heading', { name: 'Unused category' }),
    ).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('button', { name: 'Delete category Unused category' })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Unused category' }),
    ).toHaveCount(0);

    await page
        .getByRole('button', { name: 'Edit Haircut and beard trim' })
        .click();
    const updatedEditor = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Save changes' }),
    });
    await updatedEditor.getByLabel('Available for visit records').check();
    await updatedEditor.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Active', { exact: true })).toBeVisible();
    const updatedServiceRow = page.getByRole('listitem').filter({
        has: page.getByRole('button', { name: 'Edit Haircut and beard trim' }),
    });
    await expect(
        updatedServiceRow.getByText('Hair and beard', { exact: true }),
    ).toBeVisible();

    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
