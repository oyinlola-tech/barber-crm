import { expect, test } from '@playwright/test';

test('private sign-in page exposes login and recovery without registration', async ({
    page,
}) => {
    await page.goto('/login');

    await expect(
        page.getByRole('heading', { name: 'Welcome back' }),
    ).toBeVisible();
    await expect(page.getByLabel('Email address')).toBeVisible();
    await expect(page.getByLabel('Password')).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Forgot password?' }),
    ).toHaveAttribute('href', '/forgot-password');
    await expect(
        page.getByRole('link', { name: /register|create account/i }),
    ).toHaveCount(0);
});

test('sign-in form remains usable in a narrow mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');

    await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible();
    const documentWidth = await page.evaluate(
        () => document.documentElement.scrollWidth,
    );
    expect(documentWidth).toBeLessThanOrEqual(390);
});
