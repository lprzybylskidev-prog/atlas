import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const users = {
    admin: {
        email: 'admin@example.test',
        password: 'password',
    },
    limited: {
        email: 'limited@example.test',
        password: 'password',
    },
};

async function signIn(page: Page, user: { email: string; password: string }): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(user.email);
    await page.getByLabel(/Hasło|Password/).fill(user.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await expect(page).toHaveURL('/');
}

async function openUserMenu(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Menu użytkownika|User menu/ }).click();
}

async function confirmAdministratorAccess(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill(users.admin.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

test.describe('Admin visibility', () => {
    test('hides Admin entry from users without the route permission', async ({ page }) => {
        await signIn(page, users.limited);
        await openUserMenu(page);

        await expect(page.getByRole('menuitem', { name: /Panel administratora|Admin panel/ })).toHaveCount(0);
    });

    test('shows Admin entry and module-gated system status elements for administrators', async ({ page }) => {
        await signIn(page, users.admin);
        await openUserMenu(page);

        await expect(page.getByRole('menuitem', { name: /Panel administratora|Admin panel/ })).toBeVisible();

        await page.goto('/admin');

        if (page.url().includes('/user/confirm-password')) {
            await confirmAdministratorAccess(page);
        }

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Identity module' })).toBeVisible();
        await expect(page.getByText('The deployed Identity module is available for the active team context.')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Search module' })).toHaveCount(0);
    });
});
