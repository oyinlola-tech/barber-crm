import { expect, test } from '@playwright/test';

test('authorized staff can confirm customer anonymization from a mobile profile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.privacy@example.test');
    await page
        .getByLabel('Password')
        .fill('Browser-only privacy password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/customers');
    const customer = page
        .getByRole('row')
        .filter({ hasText: 'Privacy Customer' });
    await expect(customer).toBeVisible();
    const profileHref = await customer
        .getByRole('link', { name: 'View profile' })
        .getAttribute('href');
    expect(profileHref).not.toBeNull();
    await page.goto(profileHref!);

    page.once('dialog', async (dialog) => {
        expect(dialog.message()).toContain(
            'Remove the customer’s personal details',
        );
        await dialog.accept();
    });
    await page.getByRole('button', { name: 'Anonymize customer' }).click();
    await expect(page).toHaveURL(/customers$/);
    await page.goto(profileHref!);
    await expect(page.getByText('Personal data anonymized')).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Deleted Customer', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('+2348076543210')).toHaveCount(0);
    await expect(page.getByText('private.customer@example.test')).toHaveCount(
        0,
    );
    await expect(
        page.getByText('Private note for anonymization browser coverage.'),
    ).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Anonymize customer' }),
    ).toHaveCount(0);
    expect(await page.evaluate(() => window.scrollX)).toBe(0);
});
