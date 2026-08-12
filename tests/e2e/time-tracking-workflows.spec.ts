import type { Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

const password = 'password';
const trackedUserEmail = 'tt.user.004.north@example.test';
const managerEmail = 'tt.head.manager.01.north@example.test';
const outOfScopeSessionPublicId = '01K00000000000000000000002';
const maintenanceSessionPublicId = '01K00000000000000000000001';

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(email);
    await page.getByLabel(/Hasło|Password/).fill(password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    await completeSignIn(page, 'TT Demo Team North');
}

async function signOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Menu użytkownika|User menu/ }).click();
    await page.getByRole('menuitem', { name: /Wyloguj|Log out/ }).click();
    await expect(page).toHaveURL(/\/login$/);
}

async function openUserMenu(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Menu użytkownika|User menu/ }).click();
    await expect(page.getByRole('menu', { name: /Menu użytkownika|User menu/ })).toBeVisible();
}

async function confirmAdminMode(page: Page): Promise<void> {
    if (!page.url().includes('/user/confirm-password')) {
        return;
    }

    await page.getByLabel(/Hasło|Password/).fill(password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

async function ensureEnglishLocale(page: Page): Promise<void> {
    if (await page.getByRole('button', { name: 'Zmień język' }).isVisible()) {
        await page.getByRole('button', { name: 'Zmień język' }).click();
    }

    await expect(page.getByRole('button', { name: 'Change language' })).toBeVisible();
}

async function chooseOption(page: Page, label: RegExp, option: RegExp): Promise<void> {
    await page.getByRole('combobox', { name: label }).click();
    await page.getByRole('option', { name: option }).click();
}

async function settlePage(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle', { timeout: 1500 }).catch(() => undefined);
}

async function waitForMeasurableInterval(): Promise<void> {
    const confirmedStartSecond = Math.floor(Date.now() / 1000);
    await expect.poll(() => Math.floor(Date.now() / 1000)).toBeGreaterThan(confirmedStartSecond);
}

test.describe.serial('TimeTracking browser workflows', () => {
    test('completes the mobile user session, break, other-work and correction lifecycle in Polish', async ({ page }, testInfo) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await signIn(page, trackedUserEmail);

        await page.goto('/user/work-time?range=year');
        await expect(page.getByRole('heading', { level: 1, name: 'Czas pracy' })).toBeVisible();
        await expect(page.getByText('Podsumowanie dzienne', { exact: true }).first()).toBeVisible();

        await openUserMenu(page);
        await page.getByRole('menuitem', { name: 'Idź na przerwę' }).click();
        await expect(page.getByRole('heading', { name: 'Przerwa jest aktywna' })).toBeVisible();
        await waitForMeasurableInterval();

        await page.getByLabel('Aktualne hasło').fill(password);
        const [breakReturnResponse] = await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'GET' && new URL(response.url()).pathname === '/'),
            page.getByRole('button', { name: 'Wróć do pracy' }).click(),
        ]);
        await breakReturnResponse.finished();
        await expect(page).toHaveURL('/');

        await openUserMenu(page);
        await page.getByRole('menuitem', { name: 'Rozpocznij pracę poza komputerem' }).click();
        await expect(page.getByRole('heading', { name: 'Rozpocznij pracę poza komputerem' })).toBeVisible();
        await chooseOption(page, /^Kategoria$/, /Telefon do sądu/);

        const otherWorkDescription = `E2E praca poza komputerem ${testInfo.project.name}`;
        await page.getByLabel('Opis pracy').fill(otherWorkDescription);
        await page.getByRole('button', { name: 'Rozpocznij pracę poza komputerem' }).click();
        await expect(page.getByRole('heading', { name: 'Praca poza komputerem jest aktywna' })).toBeVisible();
        await expect(page.getByText(otherWorkDescription)).toBeVisible();
        await waitForMeasurableInterval();
        await page.getByLabel('Notatka końcowa').fill('Powrót do pracy potwierdzony w E2E.');
        await page.getByLabel('Aktualne hasło').fill(password);
        const [otherWorkReturnResponse] = await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'GET' && new URL(response.url()).pathname === '/'),
            page.getByRole('button', { name: 'Wróć do pracy' }).click(),
        ]);
        await otherWorkReturnResponse.finished();
        await expect(page).toHaveURL('/');

        await page.goto('/user/work-time?section=work_sessions&range=year');
        await expect(page.getByText('Sesje pracy', { exact: true }).first()).toBeVisible();
        await page.getByRole('button', { name: 'Zgłoś korektę' }).first().click();

        const correctionDescription = `E2E korekta czasu ${testInfo.project.name}`;
        await page.getByLabel('Opis korekty').fill(correctionDescription);
        const [, correctionReturnResponse] = await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && new URL(response.url()).pathname === '/user/work-time/corrections',
            ),
            page.waitForResponse((response) => {
                const url = new URL(response.url());

                return (
                    response.request().method() === 'GET' &&
                    url.pathname === '/user/work-time' &&
                    url.searchParams.get('section') === 'work_sessions'
                );
            }),
            page.getByRole('button', { name: 'Wyślij zgłoszenie' }).click(),
        ]);
        await correctionReturnResponse.finished();
        await expect(page.getByRole('button', { name: 'Wyślij zgłoszenie' })).toHaveCount(0);
        await page.goto('/user/work-time?section=corrections&range=year');
        await expect(page.getByText(correctionDescription)).toBeVisible();
        await expect(page.getByText('Oczekujące', { exact: true }).first()).toBeVisible();
    });

    test('proves manager scope, report composition, decisions, notification delivery and legacy-route removal', async ({
        page,
    }, testInfo) => {
        await signIn(page, managerEmail);

        await page.goto('/manager/work-time/summary?range=year');
        await expect(page.getByRole('heading', { level: 1, name: /Podsumowanie|Summary/ })).toBeVisible();
        await expect(page.getByText('TT Demo Team North').first()).toBeVisible();
        await expect(page.getByText('TT User 004 - North').first()).toBeVisible();

        const denied = await page.request.get(`/manager/work-time/work-sessions/${outOfScopeSessionPublicId}`);
        expect(denied.status()).toBe(403);

        const legacy = await page.request.get('/time-tracking/manager-report');
        expect(legacy.status()).toBe(404);

        const otherWorkDescription = `E2E praca poza komputerem ${testInfo.project.name}`;
        await page.goto(`/manager/work-time/other-work?range=year&search=${encodeURIComponent(otherWorkDescription)}`);
        const otherWorkRow = page.getByRole('row').filter({ hasText: otherWorkDescription });
        await expect(otherWorkRow).toBeVisible();
        await otherWorkRow.getByRole('button', { name: /Zatwierdź|Approve/ }).click();
        const otherWorkDialog = page.getByRole('dialog');
        await otherWorkDialog.getByLabel(/Powód|Reason/).fill('E2E manager approval.');
        await otherWorkDialog.getByRole('button', { name: /Potwierdź akcję|Confirm action/ }).click();
        await expect(otherWorkRow.getByText(/Zatwierdzone|Approved/, { exact: true })).toBeVisible();
        await settlePage(page);

        const correctionDescription = `E2E korekta czasu ${testInfo.project.name}`;
        await page.goto(`/manager/work-time/corrections?range=year&search=${encodeURIComponent(correctionDescription)}`);
        const correctionRow = page.getByRole('row').filter({ hasText: correctionDescription });
        await expect(correctionRow).toBeVisible();
        await correctionRow.getByRole('button', { name: /Odrzuć|Reject/ }).click();
        const correctionDialog = page.getByRole('dialog');
        await correctionDialog.getByLabel(/Powód|Reason/).fill('E2E manager rejection.');
        await correctionDialog.getByRole('button', { name: /Potwierdź akcję|Confirm action/ }).click();
        await expect(correctionRow.getByRole('button', { name: /Odrzuć|Reject/ })).toHaveCount(0);
        await expect(page.getByText('Odrzucone', { exact: true }).first()).toBeVisible();
        await settlePage(page);

        await signOut(page);
        await signIn(page, trackedUserEmail);
        await page.goto('/user/notifications');
        await expect(page.getByText('Decyzja dla pracy poza komputerem', { exact: true }).first()).toBeVisible();
        await expect(page.getByText('Korekta czasu pracy została rozstrzygnięta', { exact: true }).first()).toBeVisible();
    });

    test('renders English Admin operations and maintenance-affected session evidence', async ({ page }) => {
        await signIn(page, 'admin@example.test');
        await ensureEnglishLocale(page);

        await page.goto('/admin/work-time/summary?range=year');
        await confirmAdminMode(page);
        await expect(page.getByRole('heading', { level: 1, name: 'Summary' })).toBeVisible();
        await chooseOption(page, /^Team$/, /TT Demo Team North/);
        await page.getByRole('button', { name: 'Apply' }).click();
        await expect(page.getByRole('table')).toBeVisible();
        await expect(page.getByText('TT Head Manager 01 - North').first()).toBeVisible();

        await page.goto(`/admin/work-time/work-sessions/${maintenanceSessionPublicId}`);
        await expect(page.getByRole('heading', { level: 1, name: 'Work session details' })).toBeVisible();
        await expect(page.getByText('Maintenance impacts', { exact: true })).toBeVisible();
        await expect(page.getByText(/Scheduled.*Completed/)).toBeVisible();

        await page.goto('/admin/work-time/breaks?range=custom&from=2026-07-01&to=2026-07-31');
        await expect(page.getByRole('heading', { level: 1, name: 'Breaks' })).toBeVisible();
        await chooseOption(page, /^Team$/, /TT Demo Team North/);
        await page.getByRole('button', { name: 'Apply' }).click();
        await expect(page.getByText('Closed', { exact: true }).first()).toBeVisible();
        const convertExcessButton = page.getByRole('button', { name: 'Convert excess' }).first();
        await expect(convertExcessButton).toBeVisible();
        await convertExcessButton.click();
        const conversionDialog = page.getByRole('dialog');
        await conversionDialog.getByLabel('Reason').fill('E2E Admin excess-break conversion.');
        await Promise.all([
            expect(page.getByText('Break excess was converted through an audited correction.')).toBeVisible(),
            page.waitForResponse(
                (response) =>
                    response.request().method() === 'POST' &&
                    /\/admin\/work-time\/breaks\/[^/]+\/convert-excess$/.test(new URL(response.url()).pathname),
            ),
            conversionDialog.getByRole('button', { name: 'Confirm action' }).click(),
        ]);
        await settlePage(page);
        await signOut(page);
    });
});
