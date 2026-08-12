import type { Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

const manager = {
    email: 'admin@example.test',
    password: 'password',
};

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(manager.email);
    await page.getByLabel(/Hasło|Password/).fill(manager.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    await completeSignIn(page);
}

async function ensurePolishLocale(page: Page): Promise<void> {
    await page.goto('/');

    if (await page.getByRole('button', { name: 'Change language' }).isVisible()) {
        await page.getByRole('button', { name: 'Change language' }).click();
    }

    await expect(page.getByRole('button', { name: 'Zmień język' })).toBeVisible();
}

async function savePrivateView(page: Page, path: string, name: string): Promise<void> {
    await page.goto(path);
    const details = page
        .locator('details')
        .filter({ hasText: /Widoki|Views/ })
        .filter({ visible: true })
        .first();
    await expect(details.locator('summary')).toBeVisible();
    await details.evaluate((element) => {
        if (element instanceof HTMLDetailsElement) element.open = true;
    });
    await page
        .getByLabel(/Nazwa widoku|Saved view name/)
        .filter({ visible: true })
        .first()
        .fill(name);

    const mutation = page.waitForResponse((response) => {
        const url = new URL(response.url());

        return response.request().method() === 'POST' && url.pathname === '/table-views' && response.status() < 400;
    });
    const currentPath = new URL(page.url()).pathname;
    const refreshedTable = page.waitForEvent('requestfinished', {
        predicate: (request) => {
            const url = new URL(request.url());

            return request.method() === 'GET' && url.pathname === currentPath;
        },
    });
    await page
        .getByRole('button', { name: /Zapisz|Save/ })
        .filter({ visible: true })
        .first()
        .click();
    await Promise.all([mutation, refreshedTable]);
    await expect
        .poll(() =>
            page.evaluate((savedName) => {
                const state = window.history.state as { page?: { props?: Record<string, unknown> } } | null;
                const props = state?.page?.props ?? {};

                return Object.values(props).some((value) => {
                    if (typeof value !== 'object' || value === null || !('savedViews' in value)) return false;
                    const views = (value as { savedViews?: Array<{ name?: unknown }> }).savedViews ?? [];

                    return views.some((view) => view.name === savedName);
                });
            }, name),
        )
        .toBe(true);
    await page.waitForLoadState('networkidle');
}

test.describe('shell-neutral saved views', () => {
    test('saves views from notification, user-report, and manager-operation tables in PL and EN without Admin endpoints', async ({
        page,
    }) => {
        test.setTimeout(180000);
        await signIn(page);
        await ensurePolishLocale(page);

        await savePrivateView(page, '/user/notifications', 'E2E notification view');
        await savePrivateView(page, '/user/work-time', 'E2E user report view');
        await savePrivateView(page, '/manager/work-time/work-sessions', 'E2E manager operations view');

        await page.goto('/');
        await Promise.all([
            page.waitForEvent('requestfinished', {
                predicate: (request) => new URL(request.url()).pathname === '/' && request.method() === 'GET',
            }),
            page.getByRole('button', { name: 'Zmień język' }).click(),
        ]);
        await expect(page.getByRole('button', { name: 'Change language' })).toBeVisible();
        await savePrivateView(page, '/manager/work-time/summary', 'E2E manager summary view');
    });
});
