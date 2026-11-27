import { expect, test } from '@playwright/test';

test('owner can update recorded customer feedback on a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.review-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page.getByLabel('Recovery code').fill('browser-review-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/reviews');
    const review = page.locator('li').filter({ hasText: 'Inbox Customer' });
    await expect(review).toBeVisible();
    await review.getByRole('button', { name: 'Edit feedback' }).click();
    await review
        .getByLabel('Customer feedback')
        .fill('The customer confirmed that the visit was excellent.');
    await review.getByRole('button', { name: 'Save feedback' }).click();
    await expect(
        review.getByText(
            'The customer confirmed that the visit was excellent.',
        ),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Export CSV' })).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
