import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const users = {
    admin: {
        email: 'admin@example.test',
        password: 'password',
    },
};

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(users.admin.email);
    await page.getByLabel(/Hasło|Password/).fill(users.admin.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    const continueHere = page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ });

    try {
        await expect(continueHere).toBeVisible({ timeout: 1000 });
        await continueHere.click();
    } catch {
        // No active-session conflict was shown.
    }

    await expect(page).toHaveURL('/');
}

async function confirmAdministratorAccess(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill(users.admin.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

test.describe('Admin impersonation', () => {
    test('lets an administrator start and exit impersonation from user administration', async ({ page }) => {
        await signIn(page);
        await page.goto('/admin/users');

        if (page.url().includes('/user/confirm-password')) {
            await confirmAdministratorAccess(page);
        }

        await expect(page.getByRole('heading', { name: /Użytkownicy|Users/ })).toBeVisible();

        const targetRow = page.getByRole('row').filter({ hasText: 'limited@example.test' });
        const ownAccountRow = page.getByRole('row').filter({ hasText: 'admin@example.test' });

        await expect(targetRow).toBeVisible();
        await expect(ownAccountRow.getByRole('button', { name: /Impersonuj|Impersonate/ })).toHaveCount(0);
        await targetRow.getByRole('button', { name: /Impersonuj|Impersonate/ }).click();

        await expect(page.getByRole('heading', { name: /Impersonacja użytkownika|User impersonation|Start impersonation/ })).toBeVisible();
        await expect(
            page.getByText(/audyt zapisuje rzeczywistego administratora|audit records both the actual administrator/i),
        ).toBeVisible();
        await expect(page.getByLabel(/Nadpisz blokadę konta wrażliwego|Override sensitive-account block/)).toHaveCount(0);
        await page.getByLabel(/Powód impersonacji|Reason/).fill('E2E support verification');
        await page.getByRole('button', { name: /Rozpocznij impersonację|Start impersonation/ }).click();

        await expect(page).toHaveURL('/');
        await expect(page.getByText(/Impersonujesz użytkownika|Impersonating/)).toBeVisible();
        await expect(page.getByText('Visibility User')).toBeVisible();
        await expect(page.getByText('E2E support verification')).toBeVisible();

        await page.getByRole('button', { name: /Zakończ impersonację|Exit impersonation/ }).click();
        await expect(page).toHaveURL('/admin');
        await expect(page.getByText(/Impersonujesz użytkownika|Impersonating/)).toHaveCount(0);
    });
});
