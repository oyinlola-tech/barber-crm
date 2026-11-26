import { expect, test } from '@playwright/test';

test('read-only staff can inspect roles without seeing role management actions', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.browser@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/roles');
    await expect(
        page.getByRole('heading', { name: 'Roles and permissions' }),
    ).toBeVisible();
    await expect(page.getByText(/available permissions/)).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Create custom role' }),
    ).toHaveCount(0);

    const protectedPages = [
        ['/analytics', 'Analytics'],
        ['/staff', 'Staff'],
        ['/inbox', 'WhatsApp inbox'],
        ['/automations', 'Follow-up automations'],
        ['/messages/scheduled', 'Scheduled messages'],
        ['/templates', 'WhatsApp templates'],
        ['/reviews', 'Customer feedback'],
        ['/notifications', 'Notifications'],
        ['/operations/audit', 'Audit log'],
        ['/operations/health', 'System health'],
        ['/settings/business', 'Business profile'],
        ['/settings/whatsapp', 'WhatsApp integration'],
        ['/settings/security', 'Account security'],
    ] as const;

    for (const [path, heading] of protectedPages) {
        const response = await page.goto(path);
        expect(response?.status(), `${path} HTTP status`).toBe(200);
        await expect(
            page.getByRole('heading', { name: heading, exact: true }),
        ).toBeVisible();
        if (path === '/reviews' || path === '/operations/audit') {
            await expect(
                page.getByRole('link', { name: 'Export CSV' }),
            ).toHaveCount(0);
        }
        expect(
            await page.evaluate(() => document.documentElement.scrollWidth),
            `${path} horizontal overflow`,
        ).toBeLessThanOrEqual(390);
    }
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
