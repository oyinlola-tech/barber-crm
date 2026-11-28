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
        page
            .getByRole('contentinfo')
            .getByText('Good service deserves a return visit.'),
    ).toBeVisible();
    await expect
        .poll(() =>
            page
                .getByRole('contentinfo')
                .evaluate((footer) => footer.getBoundingClientRect().width),
        )
        .toBe(1280);
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
    await expect
        .poll(() =>
            page
                .getByRole('contentinfo')
                .evaluate((footer) => footer.getBoundingClientRect().width),
        )
        .toBe(390);
    const documentWidth = await page.evaluate(
        () => document.documentElement.scrollWidth,
    );
    expect(documentWidth).toBeLessThanOrEqual(390);
});

test('split sign-in page uses the branded illustration and supports dark mode', async ({
    page,
}) => {
    await page.setViewportSize({ width: 1440, height: 960 });
    await page.goto('/login');

    await expect(
        page.getByRole('heading', {
            name: 'Keep the good conversations going.',
        }),
    ).toBeVisible();
    await expect(
        page.getByRole('img', {
            name: 'Illustration of a quiet barbershop, ready for the next customer',
        }),
    ).toBeVisible();
    await expect
        .poll(() =>
            page
                .getByRole('contentinfo')
                .evaluate((footer) => footer.getBoundingClientRect().width),
        )
        .toBe(1440);
    await page.screenshot({
        path: '/tmp/barber-crm-login-desktop.png',
        fullPage: true,
    });
    await page.getByRole('button', { name: 'Switch to dark theme' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({
        path: '/tmp/barber-crm-login-dark.png',
        fullPage: true,
    });
});
