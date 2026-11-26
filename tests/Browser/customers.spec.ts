import { expect, test } from '@playwright/test';

test('an authenticated staff member opens the dashboard and customer list', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.browser@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/dashboard/);
    await expect(
        page.getByRole('heading', { name: 'Browser Test Barbershop' }),
    ).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
    if (process.env.VISUAL_REVIEW === '1') {
        await page.screenshot({
            path: '/tmp/barber-crm-dashboard-mobile.png',
            fullPage: true,
        });
    }

    await page.goto('/customers');
    await expect(
        page.getByRole('heading', { name: 'Customers' }),
    ).toBeVisible();
    await expect(
        page.getByText('Add a customer', { exact: true }),
    ).toBeVisible();
    await page.locator('summary').filter({ hasText: 'Add a customer' }).click();
    await page.getByLabel('First name').fill('Ada');
    await page.getByLabel('Last name').fill('Okafor');
    await page.getByLabel('WhatsApp phone').fill('+2348012345678');
    await page.getByRole('button', { name: 'Save customer' }).click();
    await expect(
        page.getByRole('heading', { name: 'Ada Okafor' }),
    ).toBeVisible();

    await expect(page.getByText('No consent recorded')).toBeVisible();
    await page.getByLabel('Consent source').selectOption('written');
    await page.getByRole('button', { name: 'Record consent' }).click();
    await expect(page.getByText('Opted in')).toBeVisible();

    await page.getByLabel('Add a private note').fill('Prefers a low fade.');
    await page.getByRole('button', { name: 'Add note' }).click();
    await expect(page.getByText('Prefers a low fade.')).toBeVisible();

    await page.getByLabel('Tags').fill('returning, low-fade');
    await page.getByRole('button', { name: 'Save tags' }).click();
    await expect(page.getByLabel('Tags')).toHaveValue('returning, low-fade');

    await page.getByLabel('Visit note').fill('First visit record');
    const visitDate = await page
        .getByLabel('Visit date and time')
        .evaluate((input) => {
            const field = input as HTMLInputElement;
            return {
                value: field.value,
                max: field.max,
                message: field.validationMessage,
            };
        });
    expect(visitDate.value <= visitDate.max, JSON.stringify(visitDate)).toBe(
        true,
    );
    await page.getByRole('button', { name: 'Record visit' }).click();
    await expect(
        page.getByRole('listitem').getByText('First visit record'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Correct visit' }).click();
    await page.getByLabel('Visit note').last().fill('Corrected visit record');
    await page.getByRole('button', { name: 'Save correction' }).click();
    await expect(
        page.getByRole('button', { name: 'Correct visit' }),
    ).toBeVisible();
    await expect(
        page.getByRole('listitem').getByText('Corrected visit record'),
    ).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Void visit' }).click();
    await expect(page.getByText(/^Voided ·/)).toBeVisible();
    await page.getByRole('button', { name: 'Restore visit' }).click();
    await expect(
        page.getByRole('button', { name: 'Correct visit' }),
    ).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Archive customer' }).click();
    await expect(page).toHaveURL(/customers$/);
    await page.getByLabel('Customer status').selectOption('archived');
    await page.getByRole('button', { name: 'Search' }).click();
    await expect(page.getByRole('link', { name: 'Ada Okafor' })).toBeVisible();
    await expect(
        page
            .getByRole('row')
            .filter({ hasText: 'Ada Okafor' })
            .getByText('Archived', { exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'View profile' }).click();
    await expect(page.getByText('Customer archived')).toBeVisible();
    await page.getByRole('button', { name: 'Restore customer' }).click();
    await expect(
        page.getByRole('button', { name: 'Archive customer' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Restore customer' }),
    ).toHaveCount(0);

    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
    if (process.env.VISUAL_REVIEW === '1') {
        await page.screenshot({
            path: '/tmp/barber-crm-customer-mobile.png',
            fullPage: true,
        });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto('/dashboard');
        await page.screenshot({
            path: '/tmp/barber-crm-dashboard-desktop.png',
            fullPage: true,
        });
        await page
            .getByRole('button', { name: 'Switch to dark theme' })
            .click();
        await page.screenshot({
            path: '/tmp/barber-crm-dashboard-dark.png',
            fullPage: true,
        });
    }
});
