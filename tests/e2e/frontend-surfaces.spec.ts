import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const adminUser = {
    email: 'admin@example.test',
    password: 'password',
};

const applicationRoutes = ['/notifications'];

const adminRoutes = [
    '/admin',
    '/admin/users',
    '/admin/users/create',
    '/admin/teams',
    '/admin/teams/create',
    '/admin/managers',
    '/admin/authorization/roles',
    '/admin/authorization/roles/create',
    '/admin/authorization/packages',
    '/admin/authorization/packages/create',
    '/admin/authorization/permissions',
    '/admin/audit',
    '/admin/audit/security-history',
    '/admin/rate-limits',
    '/admin/logs',
    '/admin/queues',
    '/admin/files',
    '/admin/modules',
    '/admin/modules/identity',
    '/admin/integrations',
    '/admin/search',
    '/admin/managed-processes',
    '/admin/managed-processes/imports',
    '/admin/managed-processes/definitions',
    '/admin/managed-processes/schedules',
    '/admin/imports',
];

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(adminUser.email);
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
    test('renders current static frontend surfaces through the shared shells in light and dark themes', async ({ page }) => {
        test.setTimeout(120000);

        await signIn(page);
        await sweepFrontendRoutes(page);

        await page.goto('/');
        await ensureDarkTheme(page);
        await sweepFrontendRoutes(page);
    });
});
