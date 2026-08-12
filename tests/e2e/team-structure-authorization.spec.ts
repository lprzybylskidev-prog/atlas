import type { Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

async function signInAsAdmin(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    await completeSignIn(page);

    await page.goto('/admin/teams');

    if (page.url().includes('/user/confirm-password')) {
        await page.getByLabel(/Hasło|Password/).fill('password');
        await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
    }
}

async function openVisibilityTeamStructure(page: Page): Promise<void> {
    const teamRow = page.getByRole('row').filter({ hasText: 'E2E Visibility Team' });
    await teamRow.getByRole('button', { name: /Edytuj|Edit/ }).click();
    await page
        .getByRole('button', { name: /Struktura zespołu|Team structure/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/structure/);
}

async function loadManager(page: Page, name: string): Promise<void> {
    const managerSelect = page.getByLabel(/Użytkownicy|Users/);
    await managerSelect.click();
    await page.getByRole('option').filter({ hasText: name }).click();
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('preview_manager=')),
        page.getByRole('button', { name: /Załaduj|Load/ }).click(),
    ]);
    await expect(page.getByRole('heading', { name, exact: true }).first()).toBeVisible();
}

test.describe('Integrated team structure and authorization workflow', () => {
    test('administers membership, head manager and atomic reparent from Team Structure on desktop', async ({ page }) => {
        await signInAsAdmin(page);
        await expect(page.locator('a[href="/admin/managers"]')).toHaveCount(0);
        await openVisibilityTeamStructure(page);

        await expect(page.getByRole('heading', { name: /Aktywni członkowie zespołu|Active team members/ })).toBeVisible();
        await expect(page.getByText('Visibility Admin', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('Visibility User', { exact: true }).first()).toBeVisible();

        await page.getByLabel(/^Użytkownik$|^User$/).click();
        await page.getByRole('option').filter({ hasText: 'Structure Candidate' }).click();
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/structure/members')),
            page.getByRole('button', { name: /Dodaj członka|Add member/ }).click(),
        ]);
        await expect(page.getByText('Structure Candidate', { exact: true }).first()).toBeVisible();

        const history = page.getByText(/Historia członkostwa|Membership history/, { exact: true });
        await history.focus();
        await expect(history).toBeFocused();
        await history.press('Enter');
        await expect(page.getByText(/Aktywne|Active/, { exact: true }).first()).toBeVisible();

        await loadManager(page, 'Visibility Admin');
        const headCard = page.getByTestId('head-manager-card');
        await headCard.getByLabel(/Status managera|Manager status/).click();
        await page
            .getByRole('option', { name: /Główny manager|Head manager/ })
            .last()
            .click();
        await headCard.getByLabel(/Powód|Reason/).fill('E2E head-manager acceptance.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'PATCH' && response.url().endsWith('/structure/head-manager'),
            ),
            page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('preview_manager=')),
            headCard.getByRole('button', { name: /Zapisz status|Save status/ }).click(),
        ]);

        await loadManager(page, 'Visibility Admin');
        const reportRow = page.locator('[data-testid^="manager-relationship-"]').filter({ hasText: 'Visibility User' });
        await reportRow.getByLabel(/Nowy manager|New manager/).click();
        await page.getByRole('option').filter({ hasText: 'Structure Candidate' }).click();
        await reportRow.getByLabel(/Powód przeniesienia|Move reason/).fill('E2E atomic move.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/reparent')),
            page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('preview_manager=')),
            reportRow.getByRole('button', { name: /Przenieś podwładnego|Move report/ }).click(),
        ]);

        await expect(page.getByRole('heading', { name: 'Structure Candidate', exact: true }).first()).toBeVisible();
        await expect(page.getByText('Visibility User', { exact: true }).last()).toBeVisible();
        await page.reload();
        await expect(page.getByText('Visibility User', { exact: true }).last()).toBeVisible();

        const movedReportRow = page.locator('[data-testid^="manager-relationship-"]').filter({ hasText: 'Visibility User' });
        await movedReportRow.getByLabel(/Nowy manager|New manager/).click();
        await page.getByRole('option').filter({ hasText: 'Visibility Admin' }).click();
        await movedReportRow.getByLabel(/Powód przeniesienia|Move reason/).fill('Restore deterministic E2E hierarchy.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/reparent')),
            page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('preview_manager=')),
            movedReportRow.getByRole('button', { name: /Przenieś podwładnego|Move report/ }).click(),
        ]);

        const candidateMember = page.locator('[data-testid^="team-member-"]').filter({ hasText: 'Structure Candidate' });
        await candidateMember.getByLabel(/Powód zakończenia|End reason/).fill('E2E membership lifecycle complete.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/structure/members/')),
            candidateMember.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click(),
        ]);
        await expect(candidateMember).toHaveCount(0);
        const completedHistory = page.getByText(/Historia członkostwa|Membership history/, { exact: true });
        await completedHistory.press('Enter');
        await expect(page.getByText('Structure Candidate', { exact: true }).first()).toBeVisible();
    });

    test('keeps the critical Team Structure controls keyboard-accessible on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await signInAsAdmin(page);
        await expect(page.locator('a[href="/admin/managers"]')).toHaveCount(0);
        await openVisibilityTeamStructure(page);

        await expect(page.getByRole('heading', { name: /Struktura zespołu|Team structure/, exact: true }).first()).toBeVisible();
        const history = page.getByText(/Historia członkostwa|Membership history/, { exact: true });
        await history.focus();
        await expect(history).toBeFocused();
        await history.press('Enter');
        await expect(page.getByText('Visibility Admin', { exact: true }).first()).toBeVisible();

        const teamSelect = page.getByLabel(/Zespół|Team/).first();
        await teamSelect.click();
        await Promise.all([
            page.waitForEvent('requestfinished', {
                predicate: (request) => request.method() === 'GET' && new URL(request.url()).pathname.endsWith('/structure'),
            }),
            page.getByRole('option', { name: 'Administration', exact: true }).click(),
        ]);
        await expect(page.getByLabel(/Użytkownicy|Users/)).toHaveText(/Wybierz użytkownika|Select user/);

        await page
            .getByLabel(/Zespół|Team/)
            .first()
            .click();
        await Promise.all([
            page.waitForEvent('requestfinished', {
                predicate: (request) => request.method() === 'GET' && new URL(request.url()).pathname.endsWith('/structure'),
            }),
            page.getByRole('option', { name: 'E2E Visibility Team', exact: true }).click(),
        ]);

        const managerSelect = page.getByLabel(/Użytkownicy|Users/);
        await managerSelect.click();
        const existingManagerOption = page.getByRole('option').filter({ hasText: 'Visibility Admin' });
        await expect(existingManagerOption).toContainText(/Manager|manager/);
        await existingManagerOption.click();
        await Promise.all([
            page.waitForEvent('requestfinished', {
                predicate: (request) => {
                    const url = new URL(request.url());

                    return request.method() === 'GET' && url.pathname.endsWith('/structure') && url.searchParams.has('preview_manager');
                },
            }),
            page.getByRole('button', { name: /Załaduj|Load/ }).click(),
        ]);
        await expect(page.getByRole('heading', { name: /Struktura zespołu|Team hierarchy/, exact: true }).last()).toBeVisible();
    });
});
