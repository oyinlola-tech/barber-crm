import { expect, test } from '@playwright/test';

test('dashboard remains useful and readable on mobile and desktop in both themes', async ({
    page,
}) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.roles@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);
    await expect(
        page.getByRole('heading', { name: /Good to see you at/ }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'New faces in the shop' }),
    ).toBeVisible();

    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.screenshot({
        path: '/tmp/barber-crm-dashboard-desktop.png',
        fullPage: true,
    });
    await page.getByRole('button', { name: 'Switch to dark theme' }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({
        path: '/tmp/barber-crm-dashboard-dark.png',
        fullPage: true,
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Switch to light theme' }).click();
    await page.screenshot({
        path: '/tmp/barber-crm-dashboard-mobile.png',
        fullPage: true,
    });
    await expect(
        page.getByRole('navigation', { name: 'Mobile navigation' }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('navigation', { name: 'Mobile navigation' })
            .getByRole('link', { name: 'Customers' }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Open more navigation' }).click();
    await expect(
        page.getByRole('navigation', { name: 'More navigation' }),
    ).toBeVisible();

    const documentWidth = await page.evaluate(
        () => document.documentElement.scrollWidth,
    );
    expect(documentWidth).toBeLessThanOrEqual(390);
});

test('browser push is presented as opt-in and stays disabled until configured', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.roles@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);
    await page.goto('/settings/security');
    await expect(
        page.getByRole('heading', { name: 'Browser notifications' }),
    ).toBeVisible();
    await expect(
        page.getByText(/waiting for the administrator to configure VAPID keys/),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Turn on notifications' }),
    ).toBeDisabled();
});
