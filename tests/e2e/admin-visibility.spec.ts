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

async function openUserMenu(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Menu użytkownika|User menu/ }).click();
}

async function confirmAdministratorAccess(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill(users.admin.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

test.describe('Admin visibility', () => {
    test('renders Auth and application table surfaces through the shared shell', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: /Zaloguj się|Log in/ })).toBeVisible();

        await signIn(page, users.admin);
        await page.goto('/user/notifications');

        const main = page.getByRole('main');

        await expect(page.getByRole('heading', { name: /Powiadomienia|Notifications/, exact: true })).toBeVisible();
        await expect(main.getByRole('table')).toBeVisible();
    });

    test('hides Admin entry from users without the route permission', async ({ page }) => {
        await signIn(page, users.limited);
        await openUserMenu(page);

        await expect(page.getByRole('menuitem', { name: /Panel administratora|Admin panel/ })).toHaveCount(0);
    });

    test('shows Admin entry and rebuilt dashboard for administrators', async ({ page }) => {
        await signIn(page, users.admin);
        await openUserMenu(page);

        await expect(page.getByRole('menuitem', { name: /Panel administratora|Admin panel/ })).toBeVisible();

        await page.goto('/admin');

        if (page.url().includes('/user/confirm-password')) {
            await confirmAdministratorAccess(page);
        }

        const main = page.getByRole('main');

        await expect(page.getByRole('heading', { name: /Pulpit administratora|Admin dashboard/, exact: true })).toBeVisible();
        await expect(main.getByRole('heading', { name: /Wydanie|Release/, exact: true })).toBeVisible();
        await expect(main.getByRole('heading', { name: /Mechanizmy zewnętrzne|External mechanisms/, exact: true })).toBeVisible();
        await expect(main.getByRole('heading', { name: /Moduły|Modules/, exact: true })).toBeVisible();
        await expect(main.getByText('Active runs, failures, warnings, schedules, and process backlog.', { exact: true })).toHaveCount(0);
        await expect(main.getByText(/Wymagany|Required|Opcjonalny|Optional|Próg świeżości|Freshness threshold/)).toHaveCount(0);
        await expect(main.getByText(/Tożsamość|Identity/, { exact: true })).toBeVisible();
        await expect(main.getByText(/Wyszukiwanie|Search/, { exact: true })).toBeVisible();

        await main.getByText(/Zdrowie systemu|System health/, { exact: true }).hover();
        await expect(page.getByRole('tooltip')).toContainText(/Szczegóły będą w widoku modułu|Details belong in the module view/);
    });
});
