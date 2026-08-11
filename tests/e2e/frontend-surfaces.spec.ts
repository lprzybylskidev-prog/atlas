import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const adminUser = {
    email: 'admin@example.test',
    password: 'password',
};

const applicationRoutes = ['/', '/user', '/user/notifications', '/manager'];

const adminRoutes = [
    '/admin',
    '/admin/audit',
    '/admin/audit/security-history',
    '/admin/authorization/packages',
    '/admin/authorization/packages/create',
    '/admin/authorization/permissions',
    '/admin/authorization/roles',
    '/admin/authorization/roles/create',
    '/admin/feature-flags',
    '/admin/files',
    '/admin/integrations',
    '/admin/logs',
    '/admin/managed-processes',
    '/admin/managed-processes/definitions',
    '/admin/managed-processes/schedules',
    '/admin/managed-processes/schedules/create',
    '/admin/modules',
    '/admin/privacy-retention',
    '/admin/privacy-retention/legal-holds',
    '/admin/privacy-retention/legal-holds/create',
    '/admin/privacy-retention/operations',
    '/admin/queues',
    '/admin/rate-limits',
    '/admin/search',
    '/admin/teams',
    '/admin/teams/create',
    '/admin/users',
    '/admin/users/create',
];

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(adminUser.email);
    await page.getByLabel(/Hasło|Password/).fill(adminUser.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    if (
        await page
            .waitForURL('/', { timeout: 2000 })
            .then(() => true)
            .catch(() => false)
    ) {
        await waitForIdle(page);
        return;
    }

    await page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ }).click();
    await expect(page).toHaveURL('/');
    await waitForIdle(page);
}

async function confirmAdminPasswordIfNeeded(page: Page): Promise<void> {
    if (!page.url().includes('/user/confirm-password')) {
        return;
    }

    await page.getByLabel(/Hasło|Password/).fill(adminUser.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
    await page.waitForURL(/\/admin(?:$|[/?#])/);
}

async function ensureDarkTheme(page: Page): Promise<void> {
    if ((await page.locator('html').getAttribute('class'))?.split(/\s+/).includes('dark') === true) {
        return;
    }

    await page.getByRole('button', { name: /Włącz ciemny motyw|Enable dark theme/ }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await waitForIdle(page);
}

async function ensurePolishLocale(page: Page): Promise<void> {
    await page.goto('/');

    if (await page.getByRole('heading', { level: 1, name: 'Application dashboard' }).isVisible()) {
        await page.getByRole('button', { name: 'Change language' }).click();
    }

    await expect(page.getByRole('heading', { level: 1, name: 'Pulpit aplikacji' })).toBeVisible();
    await waitForIdle(page);
}

async function ensureEnglishLocale(page: Page): Promise<void> {
    await page.goto('/');

    if (await page.getByRole('heading', { level: 1, name: 'Pulpit aplikacji' }).isVisible()) {
        await page.getByRole('button', { name: 'Zmień język' }).click();
    }

    await expect(page.getByRole('heading', { level: 1, name: 'Application dashboard' })).toBeVisible();
    await waitForIdle(page);
}

async function expectUsableMain(page: Page): Promise<void> {
    const main = page.getByRole('main');

    await expect(main).toBeVisible();
    await expect(main).not.toContainText('Server Error');
    await expect(main).not.toContainText('This page could not be found');
    await waitForIdle(page);
}

async function waitForIdle(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => undefined);
}

async function sweepFrontendRoutes(page: Page): Promise<void> {
    await expectUsableMain(page);

    for (const route of applicationRoutes) {
        await page.goto(route);
        await expectUsableMain(page);
    }

    for (const route of adminRoutes) {
        await page.goto(route);
        await confirmAdminPasswordIfNeeded(page);
        await expectUsableMain(page);
    }
}

test.describe('current frontend surface sweep', () => {
    test('exposes the distinct detailed Audit export', async ({ page }) => {
        await signIn(page);
        await page.goto('/admin/audit');
        await confirmAdminPasswordIfNeeded(page);
        await page.getByText(/Eksport|Export/).click();
        await expect(page.getByText(/Szczegółowy eksport historii|Detailed history export/)).toBeVisible();
    });

    test('renders current static frontend surfaces through the shared shells in light and dark themes', async ({ page }) => {
        test.setTimeout(240000);

        await signIn(page);
        await ensurePolishLocale(page);
        await sweepFrontendRoutes(page);

        await page.goto('/');
        await ensureDarkTheme(page);
        await ensureEnglishLocale(page);
        await sweepFrontendRoutes(page);
    });

    test('keeps critical migrated indexes and create workflows canonical in Polish and English', async ({ page }) => {
        test.setTimeout(120000);

        await signIn(page);
        await ensurePolishLocale(page);

        for (const surface of [
            { path: '/user', pl: 'Pulpit użytkownika', en: 'User dashboard' },
            { path: '/user/notifications', pl: 'Powiadomienia', en: 'Notifications' },
            { path: '/manager', pl: 'Pulpit managera', en: 'Manager dashboard' },
        ]) {
            await page.goto(surface.path);
            await expect(page.getByRole('heading', { level: 1, name: surface.pl })).toBeVisible();
            await waitForIdle(page);
        }

        await page.goto('/admin');
        await confirmAdminPasswordIfNeeded(page);

        for (const surface of [
            { path: '/admin/users', pl: 'Użytkownicy', en: 'Users' },
            { path: '/admin/teams', pl: 'Zespoły', en: 'Teams' },
            { path: '/admin/authorization/roles', pl: 'Role', en: 'Roles' },
            { path: '/admin/authorization/packages', pl: 'Szablony uprawnień', en: 'Authorization presets' },
            { path: '/admin/modules', pl: 'Moduły', en: 'Modules' },
        ]) {
            await page.goto(surface.path);
            await expect(page.getByRole('heading', { level: 1, name: surface.pl })).toBeVisible();
            await waitForIdle(page);
        }

        await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === '/admin/modules' &&
                    response.request().method() === 'GET' &&
                    response.status() < 400,
            ),
            page.getByRole('button', { name: 'Zmień język' }).click(),
        ]);
        await expect(page.getByRole('button', { name: 'Change language' })).toBeVisible();
        await waitForIdle(page);

        for (const surface of [
            { path: '/admin/users', en: 'Users' },
            { path: '/admin/teams', en: 'Teams' },
            { path: '/admin/authorization/roles', en: 'Roles' },
            { path: '/admin/authorization/packages', en: 'Authorization presets' },
            { path: '/admin/modules', en: 'Modules' },
        ]) {
            await page.goto(surface.path);
            await expect(page.getByRole('heading', { level: 1, name: surface.en })).toBeVisible();
            await waitForIdle(page);
        }
    });

    test('keeps regular-user and manager dashboards intentionally empty in Polish and English', async ({ page }) => {
        await signIn(page);

        if (await page.getByRole('heading', { level: 1, name: 'Application dashboard' }).isVisible()) {
            await page.getByRole('button', { name: 'Change language' }).click();
            await expect(page.getByRole('heading', { level: 1, name: 'Pulpit aplikacji' })).toBeVisible();
        }

        for (const dashboard of [
            { path: '/', title: 'Pulpit aplikacji' },
            { path: '/manager', title: 'Pulpit managera' },
        ]) {
            await page.goto(dashboard.path);
            await expect(page.getByRole('heading', { level: 1, name: dashboard.title })).toBeVisible();
            await expect(page.getByRole('main')).toBeEmpty();
        }

        await page.getByRole('button', { name: 'Zmień język' }).click();
        await expect(page.getByRole('heading', { level: 1, name: 'Manager dashboard' })).toBeVisible();
        await expect(page.getByRole('main')).toBeEmpty();

        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1, name: 'Application dashboard' })).toBeVisible();
        await expect(page.getByRole('main')).toBeEmpty();
    });
});
