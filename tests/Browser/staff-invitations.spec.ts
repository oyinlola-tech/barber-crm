import { expect, test } from '@playwright/test';

test('staff invitation form explains when secure email delivery is unavailable', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.invitation-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-invitation-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/staff');
    await expect(
        page.getByRole('heading', { name: 'Invite a staff member' }),
    ).toBeVisible();
    const invitationForm = page.locator('form').filter({
        has: page.getByRole('button', { name: 'Send invitation' }),
    });
    await expect(
        invitationForm.getByRole('combobox').getByRole('option', {
            name: 'Owner',
        }),
    ).toHaveCount(0);
    await page.getByLabel('Full name').fill('New Barber');
    await page.getByLabel('Email').fill('new.barber@example.test');
    await invitationForm.getByRole('combobox').selectOption('barber');
    await page.getByRole('button', { name: 'Send invitation' }).click();

    await expect(
        page.getByText(
            'Configure a private email delivery service before sending staff invitations.',
        ),
    ).toBeVisible();
    await expect(
        page.getByText('new.barber@example.test', { exact: true }),
    ).toHaveCount(0);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
