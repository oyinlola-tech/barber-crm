import { expect, test } from '@playwright/test';

test('owner can save business contact details from a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.business-settings-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-business-settings-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/settings/business');
    await expect(
        page.getByRole('heading', { name: 'Business profile' }),
    ).toBeVisible();
    await page.getByLabel('Business phone').fill('+2348012345678');
    await page
        .getByLabel('Business email')
        .fill('contact@browser-test-barbershop.example');
    await page.getByLabel('Business address').fill('12 Main Street, Lagos');
    await page
        .getByLabel('Public review link')
        .fill('https://reviews.example.test/browser-barbershop');
    await page
        .getByLabel('Business description')
        .fill('A friendly local barbershop.');
    await page.getByLabel('Business logo').setInputFiles({
        name: 'barbershop.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=',
            'base64',
        ),
    });
    await page.getByRole('button', { name: 'Save business details' }).click();
    await expect(
        page.getByRole('status').filter({
            hasText: 'Business details saved.',
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('img', { name: 'Current business logo' }),
    ).toBeVisible();

    await page.reload();
    await expect(page.getByLabel('Business phone')).toHaveValue(
        '+2348012345678',
    );
    await expect(page.getByLabel('Business email')).toHaveValue(
        'contact@browser-test-barbershop.example',
    );
    await expect(page.getByLabel('Business address')).toHaveValue(
        '12 Main Street, Lagos',
    );
    await expect(page.getByLabel('Public review link')).toHaveValue(
        'https://reviews.example.test/browser-barbershop',
    );
    await expect(page.getByLabel('Business description')).toHaveValue(
        'A friendly local barbershop.',
    );
    await expect(
        page.getByRole('img', { name: 'Current business logo' }),
    ).toBeVisible();
    await page.getByLabel('Remove current logo').check();
    await page.getByRole('button', { name: 'Save business details' }).click();
    await expect(
        page.getByRole('img', { name: 'Current business logo' }),
    ).toHaveCount(0);
    expect((await page.request.get('/settings/business/logo')).status()).toBe(
        404,
    );
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
