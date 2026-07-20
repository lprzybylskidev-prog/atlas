import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    if (
        await page
            .waitForURL('/', { timeout: 2000 })
            .then(() => true)
            .catch(() => false)
    ) {
        return;
    }

    await page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ }).click();
    await expect(page).toHaveURL('/');
}

async function confirmAdministratorAccess(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

test.describe('Managed processes and imports Admin UI', () => {
    test('shows seeded process logs and import execution details', async ({ page }) => {
        await signIn(page);
        await page.goto('/admin/managed-processes');

        if (page.url().includes('/user/confirm-password')) {
            await confirmAdministratorAccess(page);
        }

        await expect(page.getByRole('heading', { name: 'Managed processes' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Runs' })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText('Run filters')).toBeVisible();
        await page.getByLabel('Started from').click();
        await expect(page.getByRole('button', { name: 'Previous month' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByText('e2e.imports.debtor-ledger').first()).toBeVisible();

        await page.getByRole('link', { name: 'Imports' }).click();
        await expect(page.getByRole('heading', { name: 'Import executions' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Imports' })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText('Import filters')).toBeVisible();
        await expect(page.getByText('debtor-ledger-e2e')).toBeVisible();
        await expect(page.getByText('e2e-import-csv')).toBeVisible();

        await page
            .getByRole('row', { name: /debtor-ledger-e2e/ })
            .getByRole('button', { name: 'Open logs' })
            .click();
        await expect(page.getByRole('heading', { name: 'Import detail' })).toBeVisible();
        await expect(page.getByText('currency.unsupported_e2e').first()).toBeVisible();
        await expect(page.getByText('Process run queued.')).toBeVisible();

        await page.goto('/admin/managed-processes/definitions');
        await expect(page.getByRole('heading', { name: 'Process definitions' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Definitions' })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText('Definition filters')).toBeVisible();

        await page.getByRole('link', { name: 'Schedules' }).click();
        await expect(page.getByRole('heading', { name: 'Schedules' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Schedules' })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText('Create schedule')).toBeVisible();
        await expect(page.getByRole('textbox', { name: 'Cron expression' })).toBeVisible();
        await expect(page.getByText('Schedule filters')).toBeVisible();
        await expect(page.getByRole('combobox', { name: 'Process' }).first()).toBeVisible();
    });
});
