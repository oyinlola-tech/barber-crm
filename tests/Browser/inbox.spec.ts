import { expect, test } from '@playwright/test';

test('staff can triage an inbox conversation on a mobile browser', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.browser@example.test');
    await page.getByLabel('Password').fill('Browser-only password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/inbox');
    await page.getByRole('link', { name: /Inbox Customer/ }).click();
    await expect(
        page.getByRole('heading', { name: 'Inbox Customer', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(
            'Messaging is paused because this customer has not granted WhatsApp consent.',
        ),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Send reply' })).toHaveCount(
        0,
    );

    await page.goto('/inbox');
    await page.getByRole('link', { name: /Template Customer/ }).click();
    await expect(
        page.getByRole('heading', { name: 'Template Customer', exact: true }),
    ).toBeVisible();
    await expect(page.getByLabel('Send an approved template')).toHaveValue('');
    await page
        .getByLabel('Send an approved template')
        .selectOption({ label: 'browser_return_follow_up (en)' });
    await expect(page.getByLabel('Send an approved template')).toHaveValue('1');

    await page.goto('/inbox');
    await page.getByRole('link', { name: /Uncertain Customer/ }).click();
    await expect(page.getByRole('heading', { name: 'Uncertain Customer', exact: true })).toBeVisible();
    await expect(
        page.getByText(/A previous template send has uncertain delivery/),
    ).toBeVisible();
    await expect(
        page.getByLabel('Send an approved template'),
    ).toHaveCount(0);

    await page.goto('/inbox');
    await page.getByRole('link', { name: /Inbox Customer/ }).click();

    await page.getByLabel('Add an internal note').fill('Call back after 4pm.');
    await page.getByRole('button', { name: 'Add internal note' }).click();
    await expect(page.getByText('Call back after 4pm.')).toBeVisible();

    const assignment = page.getByLabel('Assigned staff member');
    await assignment.selectOption({ label: 'Browser Test Barber' });
    const assigneeId = await assignment.inputValue();
    await page.getByRole('button', { name: 'Save assignment' }).click();
    await expect(page.getByLabel('Assigned staff member')).toHaveValue(
        assigneeId,
    );

    await page.getByRole('button', { name: 'Mark read' }).click();
    await expect(page.getByRole('button', { name: 'Mark read' })).toHaveCount(
        0,
    );

    await page.getByRole('button', { name: 'Close conversation' }).click();
    await expect(
        page.getByRole('button', { name: 'Reopen conversation' }),
    ).toBeVisible();
    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
