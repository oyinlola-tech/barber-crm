import { expect, test } from '@playwright/test';

test('staff without note access can update customer details', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.profile-editor@example.test');
    await page
        .getByLabel('Password')
        .fill('Browser-only editor password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/customers/1');
    await expect(page.getByLabel('Staff notes')).toBeDisabled();
    await page.getByLabel('First name').fill('Updated');
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(
        page.getByRole('heading', { name: 'Updated Editor' }),
    ).toBeVisible();
    await expect(page.getByLabel('First name')).toHaveValue('Updated');
});

test('an authenticated staff member opens the dashboard and customer list', async ({
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
        page.getByRole('heading', { name: 'Customers', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('table', {
            name: 'Customer records with contact information and completed visit counts',
        }),
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
    await expect(
        page.getByLabel(
            'Customer explicitly agreed to receive WhatsApp messages. Uncheck to record an opt-out.',
        ),
    ).not.toBeChecked();
    await page.getByLabel('Consent source').selectOption('written');
    await page
        .getByLabel(
            'Customer explicitly agreed to receive WhatsApp messages. Uncheck to record an opt-out.',
        )
        .check();
    await page
        .getByLabel('Evidence reference (optional)')
        .fill('signed-consent-2026-18');
    await page.getByRole('button', { name: 'Record consent' }).click();
    await expect(page.getByText('Opted in')).toBeVisible();
    await expect(
        page.getByText('Purpose: Customer follow-up and retention messages'),
    ).toBeVisible();
    await expect(
        page.getByText('Evidence reference: signed-consent-2026-18'),
    ).toBeVisible();
    await expect(
        page.getByLabel(
            'Customer explicitly agreed to receive WhatsApp messages. Uncheck to record an opt-out.',
        ),
    ).toBeChecked();
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Stop WhatsApp messages' }).click();
    await expect(page.getByText('Opted out')).toBeVisible();
    await expect(
        page.getByLabel(
            'Customer explicitly agreed to receive WhatsApp messages. Uncheck to record an opt-out.',
        ),
    ).not.toBeChecked();
    await page.reload();
    await expect(
        page.getByLabel(
            'Customer explicitly agreed to receive WhatsApp messages. Uncheck to record an opt-out.',
        ),
    ).not.toBeChecked();

    await page.getByLabel('Add a private note').fill('Prefers a low fade.');
    await page.getByRole('button', { name: 'Add note' }).click();
    await expect(page.getByText('Prefers a low fade.')).toBeVisible();

    await page.getByLabel('Tags').fill('returning, low-fade');
    await page.getByRole('button', { name: 'Save tags' }).click();
    await expect(page.getByLabel('Tags')).toHaveValue('returning, low-fade');

    const expectedReturnDate = new Date();
    expectedReturnDate.setDate(expectedReturnDate.getDate() + 1);
    await page
        .getByLabel('Stated return date')
        .fill(expectedReturnDate.toISOString().slice(0, 10));
    await page.getByRole('button', { name: 'Save changes' }).click();
    await expect(page.getByText('Visit status:')).toContainText('expected');
    await page.getByRole('button', { name: 'Mark arrived' }).click();
    await expect(page.getByText('Visit status:')).toContainText('arrived');
    await expect(
        page
            .getByRole('listitem')
            .getByText(
                'Recorded from the customer return-date attendance check.',
            ),
    ).toBeVisible();

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

    await page.getByRole('button', { name: 'Correct visit' }).first().click();
    await page.getByLabel('Visit note').last().fill('Corrected visit record');
    await page.getByRole('button', { name: 'Save correction' }).click();
    await expect(
        page.getByRole('button', { name: 'Correct visit' }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('listitem').getByText('Corrected visit record'),
    ).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Void visit' }).first().click();
    await expect(page.getByText(/^Voided ·/)).toBeVisible();
    await page.getByRole('button', { name: 'Restore visit' }).first().click();
    await expect(
        page.getByRole('button', { name: 'Correct visit' }).first(),
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
