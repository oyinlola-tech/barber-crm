import { expect, test } from '@playwright/test';

test('owner can create, update, and delete a custom role on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.role-manager@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-roles-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/roles');
    await page.getByLabel('Role name').fill('Retention coordinator');
    await page
        .locator('label')
        .filter({ hasText: 'customers.view' })
        .locator('input[type="checkbox"]')
        .check();
    await page.getByRole('button', { name: 'Create role' }).click();

    const roleCard = page.locator('article').filter({
        has: page.getByRole('heading', { name: 'Retention coordinator' }),
    });
    await expect(roleCard).toContainText('customers.view');
    await roleCard.getByRole('button', { name: 'Edit permissions' }).click();
    await expect(
        page.getByRole('heading', { name: 'Edit Retention coordinator' }),
    ).toBeVisible();
    const editor = page.locator('section').filter({
        has: page.getByRole('heading', { name: 'Edit Retention coordinator' }),
    });
    await editor
        .locator('label')
        .filter({ hasText: 'customers.view' })
        .locator('input[type="checkbox"]')
        .uncheck();
    await editor
        .locator('label')
        .filter({ hasText: 'customers.export' })
        .locator('input[type="checkbox"]')
        .check();
    await editor.getByRole('button', { name: 'Save changes' }).click();

    await page.reload();
    const updatedRole = page.locator('article').filter({
        has: page.getByRole('heading', { name: 'Retention coordinator' }),
    });
    await expect(updatedRole).toContainText('customers.export');
    await expect(updatedRole).not.toContainText('customers.view');

    page.on('dialog', (dialog) => dialog.accept());
    await updatedRole
        .getByRole('button', { name: 'Delete', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: 'Retention coordinator' }),
    ).toHaveCount(0);
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});

test('read-only staff can inspect roles without seeing role management actions', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.roles@example.test');
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
    await expect(
        page.getByRole('button', { name: 'Edit permissions' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Delete', exact: true }),
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
