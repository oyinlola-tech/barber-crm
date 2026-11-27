import { expect, test } from '@playwright/test';

test('authorized staff can import new customers and export the resulting records', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/login');
    await page.getByLabel('Email address').fill('crm.importer@example.test');
    await page
        .getByLabel('Password')
        .fill('Browser-only importer password 9842!');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page).toHaveURL(/dashboard/);

    await page.goto('/customers');
    await expect(
        page.getByRole('heading', { name: 'Customers', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Export CSV' })).toBeVisible();
    await page.getByLabel('CSV file').setInputFiles({
        name: 'new-customers.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(
            'first_name,last_name,phone,email,date_of_birth,notes\nMina,Okeke,+2348033334444,mina@example.test,,\nMina Duplicate,Okeke,+2348033334444,duplicate@example.test,,',
        ),
    });
    await page.getByRole('button', { name: 'Import CSV' }).click();

    await expect(page.getByRole('status')).toContainText(
        'Created 1 customer; skipped 1 row.',
    );
    await expect(page.getByRole('status')).toContainText(
        'Duplicate normalized phone number within this file',
    );
    await expect(page.getByRole('link', { name: 'Mina Okeke' })).toBeVisible();
    await expect(
        page.getByRole('link', { name: 'Mina Duplicate Okeke' }),
    ).toHaveCount(0);

    const response = await page.request.get('/customers/export');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/csv');
    expect(response.headers()['cache-control']).toContain('no-store');
    const csv = await response.text();
    expect(csv).toContain("Mina,Okeke,'+2348033334444,mina@example.test");
    expect(csv).not.toContain('Mina Duplicate');
    await page.mouse.wheel(1000, 0);
    expect(await page.evaluate(() => window.scrollX)).toBe(0);
});
