import type { Page, TestInfo } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

async function waitForLoadedAssets(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all(
            Array.from(document.images, (image) => {
                if (image.complete) {
                    return Promise.resolve();
                }

                return new Promise<void>((resolve) => {
                    image.addEventListener('load', () => resolve(), { once: true });
                    image.addEventListener('error', () => resolve(), { once: true });
                });
            }),
        );
    });
}

async function signInAsAdmin(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    await completeSignIn(page);

    await page.goto('/admin/teams');

    if (page.url().includes('/user/confirm-password')) {
        await page.getByLabel(/Hasło|Password/).fill('password');
        await Promise.all([page.waitForURL(/\/admin\/teams(?:$|[/?#])/), page.getByRole('button', { name: /Potwierdź|Confirm/ }).click()]);
    }
}

async function ensurePolishLocale(page: Page): Promise<void> {
    if (await page.getByRole('button', { name: 'Change language' }).isVisible()) {
        const currentPath = new URL(page.url()).pathname;
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === currentPath && response.request().method() === 'GET' && response.status() < 400,
            ),
            page.getByRole('button', { name: 'Change language' }).click(),
        ]);
    }

    await expect(page.getByRole('button', { name: 'Zmień język' })).toBeVisible();
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    await waitForLoadedAssets(page);
}

async function ensureEnglishLocale(page: Page): Promise<void> {
    if (await page.getByRole('button', { name: 'Zmień język' }).isVisible()) {
        const currentPath = new URL(page.url()).pathname;
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === currentPath && response.request().method() === 'GET' && response.status() < 400,
            ),
            page.getByRole('button', { name: 'Zmień język' }).click(),
        ]);
    }

    await expect(page.getByRole('button', { name: 'Change language' })).toBeVisible();
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    await waitForLoadedAssets(page);
}

async function ensureLightTheme(page: Page): Promise<void> {
    if ((await page.locator('html').getAttribute('class'))?.split(/\s+/).includes('dark') === true) {
        const currentPath = new URL(page.url()).pathname;
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === currentPath && response.request().method() === 'GET' && response.status() < 400,
            ),
            page.getByRole('button', { name: /Włącz jasny motyw|Enable light theme/ }).click(),
        ]);
    }

    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
}

async function ensureDarkTheme(page: Page): Promise<void> {
    if ((await page.locator('html').getAttribute('class'))?.split(/\s+/).includes('dark') !== true) {
        const currentPath = new URL(page.url()).pathname;
        await Promise.all([
            page.waitForResponse(
                (response) =>
                    new URL(response.url()).pathname === currentPath && response.request().method() === 'GET' && response.status() < 400,
            ),
            page.getByRole('button', { name: /Włącz ciemny motyw|Enable dark theme/ }).click(),
        ]);
    }

    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    await waitForLoadedAssets(page);
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => ({
        viewport: document.documentElement.clientWidth,
        page: document.documentElement.scrollWidth,
        elements: Array.from(document.querySelectorAll<HTMLElement>('body *'))
            .filter((element) => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
            .slice(0, 8)
            .map((element) => ({ tag: element.tagName, className: element.className, right: element.getBoundingClientRect().right })),
    }));

    expect(
        overflow.page,
        `the current view must fit its ${overflow.viewport}px viewport; overflow: ${JSON.stringify(overflow.elements)}`,
    ).toBeLessThanOrEqual(overflow.viewport);
}

async function openVisibilityTeamStructure(page: Page): Promise<void> {
    const currentPath = new URL(page.url()).pathname;

    if (/^\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/edit$/.test(currentPath)) {
        await page.goto(currentPath.replace(/\/edit$/, '/structure'));
        await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/structure/);
        await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);

        return;
    }

    if (new URL(page.url()).pathname !== '/admin/teams') {
        await page.goto('/admin/teams');
    }
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    const teamRow = page.getByRole('row').filter({ hasText: 'E2E Visibility Team' });
    await teamRow.getByRole('button', { name: /Edytuj|Edit/ }).click();
    await page
        .getByRole('button', { name: /Struktura zespołu|Team structure/ })
        .first()
        .click();
    await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/structure/);
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
}

async function openVisibilityTeamEdit(page: Page): Promise<void> {
    const currentPath = new URL(page.url()).pathname;

    if (/^\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/structure$/.test(currentPath)) {
        await page.goto(currentPath.replace(/\/structure$/, '/edit'));
        await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/edit/);
        await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);

        return;
    }

    if (new URL(page.url()).pathname !== '/admin/teams') {
        await page.goto('/admin/teams');
    }
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    const teamRow = page.getByRole('row').filter({ hasText: 'E2E Visibility Team' });
    await teamRow.getByRole('button', { name: /Edytuj|Edit/ }).click();
    await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/edit/);
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
}

async function openVisibilityUserEdit(page: Page): Promise<void> {
    await waitForLoadedAssets(page);

    if (new URL(page.url()).pathname !== '/admin/users') {
        await page.goto('/admin/users');
    }

    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
    await page.getByRole('textbox', { name: /Szukaj|Search/ }).fill('limited@example.test');
    const userRow = page.getByRole('row').filter({ hasText: 'limited@example.test' });
    await userRow.getByRole('button', { name: /Edytuj|Edit/ }).click();
    await expect(page).toHaveURL(/\/admin\/users\/[0-9A-HJKMNP-TV-Z]{26}\/edit/);
    await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
}

async function changeStructureRole(page: Page, memberName: string, targetRole: RegExp, reason: string): Promise<void> {
    const member = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: memberName });
    await member.getByRole('button', { name: /Zmień rolę w strukturze|Change structure role/ }).click();
    await page.getByLabel(/Nowa rola w strukturze|New structure role/).click();
    await page.getByRole('option', { name: targetRole }).click();
    await expect(page.getByTestId('structural-role-impact-preview')).toBeVisible();
    await page.getByLabel(/^Powód$|^Reason$/).fill(reason);
    await Promise.all([
        page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && response.url().endsWith('/structure/structural-role'),
        ),
        page
            .getByRole('button', { name: /Zmień rolę w strukturze|Change structure role/ })
            .last()
            .click(),
    ]);
    await expect(page.getByRole('dialog')).toHaveCount(0);
}

test.describe('Integrated team structure and authorization workflow', () => {
    test('presents member authorization as an accessible read-only disclosure on Team Edit', async ({ page }) => {
        await signInAsAdmin(page);
        await openVisibilityTeamEdit(page);

        await expect(page.getByTestId('authorization-read-only-notice')).toContainText(/tylko do odczytu|read-only/i);

        const assignment = page.locator('[data-testid^="authorization-assignment-"]').filter({ hasText: 'Visibility Admin' }).first();
        const disclosure = assignment.locator('button[aria-expanded]').first();
        const chevron = disclosure.locator('svg[data-state]');

        if ((await disclosure.getAttribute('aria-expanded')) !== 'true') {
            await disclosure.click();
        }

        await expect(assignment.getByText('Visibility Admin', { exact: true })).toHaveCount(1);
        await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
        await expect(disclosure).toContainText(/Role: \d+ · Bezpośrednie uprawnienia: \d+|Roles: \d+ · Direct permissions: \d+/);
        await expect(chevron).toBeVisible();
        await expect(chevron).toHaveAttribute('data-state', 'expanded');
        await expect(assignment.getByTestId(/authorization-assignment-source-/)).toContainText(/Źródło:|Source:/);
        await expect(assignment).not.toContainText(':source');

        const checkboxes = assignment.getByRole('checkbox');
        expect(await checkboxes.count()).toBeGreaterThan(0);
        expect(await checkboxes.evaluateAll((nodes) => nodes.every((node) => (node as HTMLInputElement).disabled))).toBe(true);

        for (const field of [
            /Wylogowanie po bezczynności|Inactivity logout/,
            /Maksymalny czas sesji|Maximum session lifetime/,
            /Dzienny limit zwykłej przerwy|Daily regular break limit/,
            /Maksymalny czas jednej przerwy|Maximum single break duration/,
        ]) {
            await expect(assignment.getByLabel(field)).toBeDisabled();
        }

        await expect(assignment.getByRole('button', { name: /Zapisz przypisania|Save assignments/ })).toHaveCount(0);
        await expect(assignment.getByRole('button', { name: /Usuń dostęp|Remove access/ })).toHaveCount(0);
        await expect(assignment.getByLabel(/Powód zmiany uprawnień|Authorization change reason/)).toHaveCount(0);
        await expect(assignment.getByLabel(/Powód usunięcia|Removal reason/)).toHaveCount(0);

        await disclosure.focus();
        await expect(disclosure).toBeFocused();
        await disclosure.press(' ');
        await expect(disclosure).toHaveAttribute('aria-expanded', 'false');
        await expect(chevron).toHaveAttribute('data-state', 'collapsed');
        await disclosure.press('Enter');
        await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
        await expect(chevron).toHaveAttribute('data-state', 'expanded');
    });

    test('keeps role-derived and overlapping direct grants truthful while a manual User Edit changes current Source', async ({
        page,
    }, testInfo: TestInfo) => {
        await signInAsAdmin(page);
        await openVisibilityUserEdit(page);

        const assignment = page.locator('[data-testid^="authorization-assignment-"]').filter({ hasText: 'E2E Visibility Team' }).first();
        const source = page.getByTestId('authorization-assignment-source-0');
        const roles = assignment
            .locator('section')
            .filter({ has: page.getByText(/^(Role|Roles)$/) })
            .first();
        const directPermissions = assignment
            .locator('section')
            .filter({ has: page.getByText(/^(Bezpośrednie uprawnienia|Direct permissions)$/) })
            .first();
        const workspaceRole = roles.getByRole('checkbox').filter({ hasText: 'workspace.access' });
        const dashboardPermission = directPermissions.getByRole('checkbox').filter({ hasText: 'dashboard' });

        if (testInfo.project.name === 'chromium') {
            await expect(source).toContainText(
                /Źródło: Kopia uprawnień od Visibility Admin|Source: Copy of permissions from Visibility Admin/,
            );
        } else {
            await expect(source).toContainText(
                /Źródło: (Kopia uprawnień od Visibility Admin|Ręcznie)|Source: (Copy of permissions from Visibility Admin|Manual)/,
            );
        }
        await expect(workspaceRole).toHaveAttribute('aria-checked', 'true');
        await expect(workspaceRole).toBeEnabled();
        await expect(dashboardPermission).toHaveAttribute('aria-checked', 'true');
        await expect(dashboardPermission).toBeDisabled();
        await expect(dashboardPermission).toContainText(/Z roli: Dostęp do przestrzeni roboczej|From role: Workspace access/);

        await workspaceRole.click();
        await expect(dashboardPermission).toHaveAttribute('aria-checked', 'true');
        await expect(dashboardPermission).toBeEnabled();
        await workspaceRole.click();
        await expect(dashboardPermission).toBeDisabled();

        await assignment.getByLabel(/Powód zmiany uprawnień|Authorization change reason/).fill('E2E manual authorization adjustment.');

        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/authorization')),
            assignment.getByRole('button', { name: /Zapisz przypisania|Save assignments/ }).click(),
        ]);

        const success = page.getByText(/Autoryzacja zespołowa została zaktualizowana|Team authorization was updated/, {
            exact: true,
        });
        await expect(success).toHaveCount(1);
        await expect(source).toContainText(/Źródło: Ręcznie|Source: Manual/);

        await page.waitForLoadState('networkidle');
        await waitForLoadedAssets(page);
        await page.goto('/admin/users');
        await expect(success).toHaveCount(0);
        await openVisibilityUserEdit(page);

        const persistedAssignment = page
            .locator('[data-testid^="authorization-assignment-"]')
            .filter({ hasText: 'E2E Visibility Team' })
            .first();
        const persistedRoles = persistedAssignment
            .locator('section')
            .filter({ has: page.getByText(/^(Role|Roles)$/) })
            .first();
        const persistedDirectPermissions = persistedAssignment
            .locator('section')
            .filter({ has: page.getByText(/^(Bezpośrednie uprawnienia|Direct permissions)$/) })
            .first();
        await persistedRoles.getByRole('checkbox').filter({ hasText: 'workspace.access' }).click();
        await expect(persistedDirectPermissions.getByRole('checkbox').filter({ hasText: 'dashboard' })).toBeEnabled();
        await expect(persistedDirectPermissions.getByRole('checkbox').filter({ hasText: 'dashboard' })).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    test('renders localized authorization and structural roles in light desktop and dark mobile modes', async ({ page }) => {
        await signInAsAdmin(page);
        await ensurePolishLocale(page);
        await ensureLightTheme(page);
        await openVisibilityTeamEdit(page);

        let assignment = page.locator('[data-testid^="authorization-assignment-"]').filter({ hasText: 'Visibility Admin' }).first();
        const lightNoticeColor = await page
            .getByTestId('authorization-read-only-notice')
            .evaluate((element) => getComputedStyle(element).backgroundColor);

        await expect(assignment.locator('button[aria-expanded]')).toContainText(/Role: \d+ · Bezpośrednie uprawnienia: \d+/);
        await expect(assignment.getByTestId(/authorization-assignment-source-/)).toContainText(/^Źródło: /);
        await expect(assignment).toContainText('Administrator systemu');
        await expect(assignment).not.toContainText(/Original source|Oryginalne źródło|:source/);

        await openVisibilityTeamStructure(page);
        await expect(page.getByRole('heading', { name: 'Główni managerowie', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Managerowie', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Pracownicy', exact: true })).toBeVisible();

        await ensureEnglishLocale(page);
        await ensureDarkTheme(page);
        await page.setViewportSize({ width: 390, height: 844 });
        await openVisibilityTeamEdit(page);

        assignment = page.locator('[data-testid^="authorization-assignment-"]').filter({ hasText: 'Visibility Admin' }).first();
        const darkNoticeColor = await page
            .getByTestId('authorization-read-only-notice')
            .evaluate((element) => getComputedStyle(element).backgroundColor);

        expect(darkNoticeColor).not.toBe(lightNoticeColor);
        await expect(assignment.locator('button[aria-expanded]')).toContainText(/Roles: \d+ · Direct permissions: \d+/);
        await expect(assignment.getByTestId(/authorization-assignment-source-/)).toContainText(/^Source: /);
        await expect(assignment).toContainText('System administrator');
        await expect(assignment).not.toContainText(/Original source|:source/);
        await expectNoHorizontalOverflow(page);

        await openVisibilityTeamStructure(page);
        await expect(page.getByRole('heading', { name: 'Head Managers', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Managers', exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Employees', exact: true })).toBeVisible();
        await expectNoHorizontalOverflow(page);

        const employee = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' });
        const disclosure = employee.locator('button[aria-controls^="member-details-"]');
        await disclosure.focus();
        await disclosure.press('Enter');
        await expect(disclosure).toBeFocused();
        await expect(disclosure).toHaveAttribute('aria-expanded', 'true');
        const disclosureId = await disclosure.getAttribute('id');
        expect(disclosureId).not.toBeNull();
        await expect(employee.getByRole('region')).toHaveAttribute('aria-labelledby', disclosureId ?? '');
    });

    test('administers the three-section additive Team Structure workflow on desktop', async ({ page }, testInfo: TestInfo) => {
        const candidateName = testInfo.project.name === 'chromium' ? 'Structure Desktop Chromium' : 'Structure Desktop Firefox';
        const candidateEmail =
            testInfo.project.name === 'chromium' ? 'structure.desktop.chromium@example.test' : 'structure.desktop.firefox@example.test';
        await signInAsAdmin(page);
        await expect(page.locator('a[href="/admin/managers"]')).toHaveCount(0);
        await openVisibilityTeamStructure(page);

        const headSection = page.getByRole('heading', { name: /^(Główni managerowie|Head Managers)$/ });
        const managerSection = page.getByRole('heading', { name: /^(Managerowie|Managers)$/ });
        const employeeSection = page.getByRole('heading', { name: /^(Pracownicy|Employees)$/ });
        await expect(headSection).toBeVisible();
        await expect(managerSection).toBeVisible();
        await expect(employeeSection).toBeVisible();
        await expect(page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility Admin' })).toHaveCount(1);
        await expect(page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' })).toHaveCount(1);
        await expect(page.locator('[role="tree"]')).toHaveCount(0);

        const headManager = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Head Manager' });
        await expect(headManager).toHaveAttribute('data-structural-role', 'head_manager');
        await expect(headManager).toContainText(/Zakres: cały zespół|Scope: whole Team/);
        await expect(headManager.getByRole('button', { name: /Przypisz managera|Assign manager/ })).toHaveCount(0);
        await expect(headManager).not.toContainText(/Bezpośredni podwładni:|Direct reports:/);

        const initialManager = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility Admin' });
        await expect(initialManager).toContainText(/Bezpośredni podwładni: 2|Direct reports: 2/);

        await page.getByLabel(/^Użytkownik$|^User$/).click();
        await page.getByRole('option', { name: candidateName }).click();
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/structure/members')),
            page.getByRole('button', { name: /Dodaj członka|Add member/ }).click(),
        ]);

        let candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        await expect(candidate).toHaveAttribute('data-structural-role', 'employee');
        await changeStructureRole(page, candidateName, /^Manager$/, 'E2E promotion for additive assignment.');

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        const report = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' });
        await expect(candidate).toHaveAttribute('data-structural-role', 'manager');
        await report.dragTo(candidate);

        if ((await page.getByRole('dialog').count()) === 0) {
            const dataTransfer = await page.evaluateHandle(() => new DataTransfer());
            await report.dispatchEvent('dragstart', { dataTransfer });
            await candidate.dispatchEvent('dragenter', { dataTransfer });
            await candidate.dispatchEvent('dragover', { dataTransfer });
            await candidate.dispatchEvent('drop', { dataTransfer });
            await report.dispatchEvent('dragend', { dataTransfer });
        }

        await expect(page.getByRole('dialog')).toContainText(
            /Istniejące relacje z managerami pozostaną bez zmian|Existing Manager relationships will remain unchanged/,
        );
        await page.getByLabel(/^Powód$|^Reason$/).fill('E2E additive manager relationship.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().endsWith('/structure/relationships'),
            ),
            page.getByRole('button', { name: /Dodaj relację z managerem|Add manager relationship/ }).click(),
        ]);

        const updatedReport = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' });
        await expect(updatedReport).toContainText(/Managerowie: 2|Managers: 2/);
        await expect(candidate).toContainText(/Bezpośredni podwładni: 1|Direct reports: 1/);

        await candidate.dragTo(initialManager);
        await page.getByLabel(/^Powód$|^Reason$/).fill('E2E Manager-to-Manager relationship.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().endsWith('/structure/relationships'),
            ),
            page.getByRole('button', { name: /Dodaj relację z managerem|Add manager relationship/ }).click(),
        ]);
        await expect(candidate).toContainText(/Managerowie: 1|Managers: 1/);
        await expect(initialManager).toContainText(/Bezpośredni podwładni: 3|Direct reports: 3/);

        await candidate.getByRole('button', { name: /Przypisz managera|Assign manager/ }).click();
        let relationshipDialog = page.getByRole('dialog');
        await relationshipDialog.getByLabel(/Manager/).click();
        await page.getByRole('option', { name: /Visibility Admin/ }).click();
        await relationshipDialog.getByLabel(/^Powód$|^Reason$/).fill('E2E duplicate rejection.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().endsWith('/structure/relationships'),
            ),
            relationshipDialog.getByRole('button', { name: /Dodaj relację z managerem|Add manager relationship/ }).click(),
        ]);
        await expect(relationshipDialog).toContainText(/Ta relacja managera jest już aktywna|manager relationship is already active/i);
        await relationshipDialog
            .getByRole('button', { name: /Anuluj|Cancel/ })
            .last()
            .click();

        await initialManager.getByRole('button', { name: /Przypisz managera|Assign manager/ }).click();
        relationshipDialog = page.getByRole('dialog');
        await relationshipDialog.getByLabel(/Manager/).click();
        await page.getByRole('option', { name: candidateName }).click();
        await relationshipDialog.getByLabel(/^Powód$|^Reason$/).fill('E2E cycle rejection.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().endsWith('/structure/relationships'),
            ),
            relationshipDialog.getByRole('button', { name: /Dodaj relację z managerem|Add manager relationship/ }).click(),
        ]);
        await expect(relationshipDialog).toContainText(/utworzyłaby cykl|would create a manager hierarchy cycle/i);
        await relationshipDialog
            .getByRole('button', { name: /Anuluj|Cancel/ })
            .last()
            .click();

        const reportDetailsToggle = updatedReport.getByRole('button', { name: /Rozwiń szczegóły|Expand details/ });
        await reportDetailsToggle.click();
        await expect(updatedReport.getByLabel(/Powód zakończenia|End reason/)).toHaveCount(0);
        await updatedReport.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click();
        let destructiveDialog = page.getByRole('dialog');
        await expect(destructiveDialog).toContainText('Visibility User');
        await destructiveDialog.getByLabel(/Powód zakończenia|End reason/).fill('Blocked E2E membership removal.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/structure/members/')),
            destructiveDialog.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click(),
        ]);
        await expect(destructiveDialog).toContainText(
            /Przed odebraniem dostępu do zespołu zakończ aktywne relacje|End active manager relationships/,
        );
        await expect(updatedReport).toHaveCount(1);
        await destructiveDialog
            .getByRole('button', { name: /Anuluj|Cancel/ })
            .last()
            .click();

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        await candidate.getByRole('button', { name: /Rozwiń szczegóły|Expand details/ }).click();
        const relationship = candidate.locator('[data-testid^="manager-relationship-"]').filter({ hasText: 'Visibility User' });
        await expect(relationship.getByLabel(/Powód zakończenia|End reason/)).toHaveCount(0);
        await relationship.getByRole('button', { name: /Usuń przypisanie|Remove assignment/ }).click();
        destructiveDialog = page.getByRole('dialog');
        await expect(destructiveDialog).toContainText(candidateName);
        await expect(destructiveDialog).toContainText('Visibility User');
        await destructiveDialog.getByLabel(/Powód zakończenia|End reason/).fill('E2E relationship cleanup.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/end')),
            destructiveDialog.getByRole('button', { name: /Usuń przypisanie|Remove assignment/ }).click(),
        ]);
        await expect(relationship).toHaveCount(0);

        await changeStructureRole(page, candidateName, /Główny manager|Head Manager/, 'E2E whole-Team transition.');
        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        await expect(candidate).toContainText(/Zakres: cały zespół|Scope: whole Team/);
        await expect(candidate.getByRole('button', { name: /Przypisz managera|Assign manager/ })).toHaveCount(0);
        await changeStructureRole(page, candidateName, /^Manager$/, 'E2E Head Manager demotion.');
        await changeStructureRole(page, candidateName, /Pracownik|Employee/, 'Restore deterministic E2E role.');

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        await expect(candidate).toHaveAttribute('data-structural-role', 'employee');
        const detailsToggle = candidate.getByRole('button', {
            name: /Rozwiń szczegóły|Zwiń szczegóły|Expand details|Collapse details/,
        });
        if ((await detailsToggle.getAttribute('aria-expanded')) !== 'true') {
            await detailsToggle.click();
        }
        await expect(candidate.getByLabel(/Powód zakończenia|End reason/)).toHaveCount(0);
        await candidate.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click();
        destructiveDialog = page.getByRole('dialog');
        await expect(destructiveDialog).toContainText(candidateName);
        await destructiveDialog.getByLabel(/Powód zakończenia|End reason/).fill('E2E membership lifecycle complete.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/structure/members/')),
            destructiveDialog.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click(),
        ]);
        await expect(candidate).toHaveCount(0);
    });

    test('keeps disclosure and relationship assignment keyboard-accessible on mobile', async ({ page }, testInfo: TestInfo) => {
        const candidateName = testInfo.project.name === 'chromium' ? 'Structure Mobile Chromium' : 'Structure Mobile Firefox';
        const candidateEmail =
            testInfo.project.name === 'chromium' ? 'structure.mobile.chromium@example.test' : 'structure.mobile.firefox@example.test';
        await page.setViewportSize({ width: 390, height: 844 });
        await signInAsAdmin(page);
        await openVisibilityTeamStructure(page);

        await page.getByLabel(/^Użytkownik$|^User$/).click();
        await page.getByRole('option', { name: candidateName }).click();
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/structure/members')),
            page.getByRole('button', { name: /Dodaj członka|Add member/ }).click(),
        ]);
        await changeStructureRole(page, candidateName, /^Manager$/, 'E2E mobile relationship manager.');

        const employee = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Report' });
        const disclosure = employee.getByRole('button', { name: /Rozwiń szczegóły|Expand details/ });
        await disclosure.focus();
        await expect(disclosure).toBeFocused();
        await disclosure.press('Enter');
        await expect(employee.getByRole('button', { name: /Zwiń szczegóły|Collapse details/ })).toHaveAttribute('aria-expanded', 'true');

        const assign = employee.getByRole('button', { name: /Przypisz managera|Assign manager/ });
        await assign.focus();
        await expect(assign).toBeFocused();
        await assign.press('Enter');
        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await dialog.getByLabel(/Manager/).click();
        await page.getByRole('option', { name: candidateName }).click();
        await dialog.getByLabel(/^Powód$|^Reason$/).fill('E2E keyboard and mobile assignment.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'POST' && response.url().endsWith('/structure/relationships'),
            ),
            dialog.getByRole('button', { name: /Dodaj relację z managerem|Add manager relationship/ }).click(),
        ]);
        await expect(dialog).toHaveCount(0);
        await expect(assign).toBeFocused();
        await expect(employee).toContainText(candidateName);
        await expect(employee).toContainText('Visibility Admin');

        const history = page.getByText(/Historia członkostwa|Membership history/, { exact: true }).last();
        await history.focus();
        await history.press('Enter');
        await expect(page.getByText('Visibility Admin', { exact: true }).last()).toBeVisible();

        let candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        let candidateDisclosure = candidate.getByRole('button', {
            name: /Rozwiń szczegóły|Expand details|Zwiń szczegóły|Collapse details/,
        });

        if ((await candidateDisclosure.getAttribute('aria-expanded')) !== 'true') {
            await candidateDisclosure.click();
        }

        const relationship = candidate.locator('[data-testid^="manager-relationship-"]').filter({ hasText: 'Structure Report' });
        await relationship.getByRole('button', { name: /Usuń przypisanie|Remove assignment/ }).click();
        let destructiveDialog = page.getByRole('dialog');
        await destructiveDialog.getByLabel(/Powód zakończenia|End reason/).fill('Restore deterministic mobile relationship fixture.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/end')),
            destructiveDialog.getByRole('button', { name: /Usuń przypisanie|Remove assignment/ }).click(),
        ]);
        await expect(destructiveDialog).toHaveCount(0);

        await changeStructureRole(page, candidateName, /Pracownik|Employee/, 'Restore deterministic mobile role.');
        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: candidateEmail });
        candidateDisclosure = candidate.getByRole('button', {
            name: /Rozwiń szczegóły|Expand details|Zwiń szczegóły|Collapse details/,
        });

        if ((await candidateDisclosure.getAttribute('aria-expanded')) !== 'true') {
            await candidateDisclosure.click();
        }

        await candidate.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click();
        destructiveDialog = page.getByRole('dialog');
        await destructiveDialog.getByLabel(/Powód zakończenia|End reason/).fill('Restore deterministic mobile membership fixture.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/structure/members/')),
            destructiveDialog.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click(),
        ]);
        await expect(candidate).toHaveCount(0);
    });
});
