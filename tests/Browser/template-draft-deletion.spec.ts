import { expect, test } from '@playwright/test';

test('owner can delete an unused local template draft on mobile', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('crm.template-owner@example.test');
    await page.getByLabel('Password').fill('Browser-only owner password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    await page
        .getByRole('button', { name: 'Use a recovery code instead' })
        .click();
    await page
        .getByLabel('Recovery code')
        .fill('browser-template-owner-recovery-code');
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/templates');
    await expect(
        page.getByRole('heading', { name: 'WhatsApp templates' }),
    ).toBeVisible();
    await page.getByLabel('Template name').fill('unused_local_draft');
    await page.getByLabel('Message body').fill('Thanks for visiting.');
    await page.getByRole('button', { name: 'Save draft' }).click();

    const draft = page.locator('article').filter({
        hasText: 'unused_local_draft',
    });
    await expect(draft).toBeVisible();
    page.once('dialog', (dialog) => dialog.accept());
    await draft.getByRole('button', { name: 'Delete draft' }).click();
    await expect(
        page.locator('article').filter({
            hasText: 'browser_return_follow_up',
        }),
    ).toBeVisible();
    await expect(draft).toHaveCount(0);

    const approvedTemplate = page.locator('article').filter({
        hasText: 'browser_return_follow_up',
    });
    await approvedTemplate
        .getByRole('button', { name: 'Map variables' })
        .click();
    await expect(
        approvedTemplate.getByRole('heading', {
            name: 'Map approved template parameters',
        }),
    ).toBeVisible();
    await approvedTemplate
        .getByLabel('Meta parameter {{1}}')
        .selectOption('business_name');
    await approvedTemplate
        .getByRole('button', { name: 'Save mapping' })
        .click();
    await expect(
        approvedTemplate.getByRole('button', { name: 'Map variables' }),
    ).toBeVisible();
    await approvedTemplate.getByRole('button', { name: 'Preview' }).click();
    await expect(approvedTemplate).toContainText(
        'Hello {{business_name}}, when might you return?',
    );

    await approvedTemplate.getByRole('button', { name: 'Disable' }).click();
    await expect(approvedTemplate).toContainText(
        'provider APPROVED · disabled',
    );
    await approvedTemplate.getByRole('button', { name: 'Enable' }).click();
    await expect(approvedTemplate).toContainText('provider APPROVED · enabled');

    expect(
        await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(390);
});
