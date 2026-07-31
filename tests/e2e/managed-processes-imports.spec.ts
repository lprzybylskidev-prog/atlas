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

        await expect(page.getByRole('heading', { name: /Procesy|Managed processes/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Uruchomienia|Runs/ })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText(/Filtry uruchomień|Run filters/)).toBeVisible();
        await page.getByLabel(/Rozpoczęto od|Started from/).click();
        await expect(page.getByRole('button', { name: /Poprzedni miesiąc|Previous month/ })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByText('e2e.imports.debtor-ledger').first()).toBeVisible();
        await expect(page.getByText('debtor-ledger-e2e')).toBeVisible();
        await expect(page.getByText('e2e-import-csv')).toBeVisible();

        await page
            .getByRole('row', { name: /debtor-ledger-e2e/ })
            .getByRole('button', { name: /Otwórz szczegóły|Open details/ })
            .click();
        await expect(page.getByRole('heading', { name: /Szczegóły uruchomienia|Process run details|Run details/ })).toBeVisible();
        await expect(page.getByText('currency.unsupported_e2e').first()).toBeVisible();
        await expect(page.getByText(/Process run queued\.|Proces .* dodany do kolejki/)).toBeVisible();

        await page.goto('/admin/managed-processes/definitions');
        await expect(page.getByRole('heading', { name: /Definicje procesów|Process definitions/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Definicje|Definitions/ })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText(/Filtry definicji|Definition filters/)).toBeVisible();

        await page.getByRole('link', { name: /Harmonogramy|Schedules/ }).click();
        await expect(page.getByRole('heading', { name: /Harmonogramy|Schedules/ })).toBeVisible();
        await expect(page.getByRole('link', { name: /Harmonogramy|Schedules/ })).toHaveAttribute('aria-current', 'page');
        await expect(page.getByText(/Utwórz harmonogram|Create schedule/)).toBeVisible();
        await page.getByRole('link', { name: /Utwórz harmonogram|Create schedule/ }).click();
        await expect(page.getByRole('heading', { name: /Utwórz harmonogram|Create schedule/, level: 1 })).toBeVisible();
        await expect(page.getByRole('textbox', { name: /Wyrażenie cron|Cron expression/ })).toBeVisible();
        await expect(page.getByRole('combobox', { name: /Proces|Process/ }).first()).toBeVisible();
    });
});
