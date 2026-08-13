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

async function openVisibilityTeamEdit(page: Page): Promise<void> {
    const teamRow = page.getByRole('row').filter({ hasText: 'E2E Visibility Team' });
    await teamRow.getByRole('button', { name: /Edytuj|Edit/ }).click();
    await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/edit/);
}

test.describe('Integrated team structure and authorization workflow', () => {
    test('presents member authorization as an accessible read-only disclosure on Team Edit', async ({ page }) => {
        await signInAsAdmin(page);
        await openVisibilityTeamEdit(page);

        await expect(page.getByTestId('authorization-read-only-notice')).toContainText(/tylko do odczytu|read-only/i);

        const assignment = page.locator('[data-testid^="authorization-assignment-"]').filter({ hasText: 'Visibility Admin' }).first();
        const disclosure = assignment.locator('button[aria-expanded]').first();
        const chevron = disclosure.locator('svg[data-state]');

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

    test('administers the three-section additive Team Structure workflow on desktop', async ({ page }) => {
        await signInAsAdmin(page);
        await expect(page.locator('a[href="/admin/managers"]')).toHaveCount(0);
        await openVisibilityTeamStructure(page);

        const headSection = page.getByRole('heading', { name: /Główni managerowie|Head Managers/, exact: true });
        const managerSection = page.getByRole('heading', { name: /Managerowie|Managers/, exact: true });
        const employeeSection = page.getByRole('heading', { name: /Pracownicy|Employees/, exact: true });
        await expect(headSection).toBeVisible();
        await expect(managerSection).toBeVisible();
        await expect(employeeSection).toBeVisible();
        await expect(page.locator('[data-testid^="team-structure-member-"]')).toHaveCount(2);
        await expect(page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility Admin' })).toHaveCount(1);
        await expect(page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' })).toHaveCount(1);
        await expect(page.locator('[role="tree"]')).toHaveCount(0);

        await page.getByLabel(/^Użytkownik$|^User$/).click();
        await page.getByRole('option').filter({ hasText: 'Structure Candidate' }).click();
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/structure/members')),
            page.getByRole('button', { name: /Dodaj członka|Add member/ }).click(),
        ]);

        let candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Candidate' });
        await expect(candidate).toHaveAttribute('data-structural-role', 'employee');
        await candidate.getByRole('button', { name: /Zmień rolę w strukturze|Change structure role/ }).click();
        await page.getByLabel(/Nowa rola w strukturze|New structure role/).click();
        await page.getByRole('option', { name: /^Manager$/ }).click();
        await expect(page.getByTestId('structural-role-impact-preview')).toContainText(/0/);
        await page.getByLabel(/^Powód$|^Reason$/).fill('E2E promotion for additive assignment.');
        await Promise.all([
            page.waitForResponse(
                (response) => response.request().method() === 'PATCH' && response.url().endsWith('/structure/structural-role'),
            ),
            page
                .getByRole('button', { name: /Zmień rolę w strukturze|Change structure role/ })
                .last()
                .click(),
        ]);

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Candidate' });
        const report = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' });
        await expect(candidate).toHaveAttribute('data-structural-role', 'manager');
        await report.dragTo(candidate);
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
        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Candidate' });
        await candidate.getByRole('button', { name: /Rozwiń szczegóły|Expand details/ }).click();
        const relationship = candidate.locator('[data-testid^="manager-relationship-"]').filter({ hasText: 'Visibility User' });
        await relationship.getByLabel(/Powód zakończenia|End reason/).fill('E2E relationship cleanup.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().endsWith('/end')),
            relationship.getByRole('button', { name: /Zakończ|End/ }).click(),
        ]);
        await expect(relationship).toHaveCount(0);

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Candidate' });
        await candidate.getByRole('button', { name: /Zmień rolę w strukturze|Change structure role/ }).click();
        await page.getByLabel(/Nowa rola w strukturze|New structure role/).click();
        await page.getByRole('option', { name: /Pracownik|Employee/ }).click();
        await expect(page.getByTestId('structural-role-impact-preview')).toContainText(/0/);
        await page.getByLabel(/^Powód$|^Reason$/).fill('Restore deterministic E2E role.');
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

        candidate = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Structure Candidate' });
        await expect(candidate).toHaveAttribute('data-structural-role', 'employee');
        const detailsToggle = candidate.getByRole('button', {
            name: /Rozwiń szczegóły|Zwiń szczegóły|Expand details|Collapse details/,
        });
        if ((await detailsToggle.getAttribute('aria-expanded')) !== 'true') {
            await detailsToggle.click();
        }
        await candidate.getByLabel(/Powód zakończenia|End reason/).fill('E2E membership lifecycle complete.');
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'DELETE' && response.url().includes('/structure/members/')),
            candidate.getByRole('button', { name: /Zakończ członkostwo|End membership/ }).click(),
        ]);
        await expect(candidate).toHaveCount(0);
    });

    test('keeps disclosure and relationship assignment keyboard-accessible on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await signInAsAdmin(page);
        await openVisibilityTeamStructure(page);

        const employee = page.locator('[data-testid^="team-structure-member-"]').filter({ hasText: 'Visibility User' });
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
        await expect(page.getByRole('option', { name: /Visibility Admin/ })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toHaveCount(0);
        await expect(assign).toBeFocused();

        const history = page.getByText(/Historia członkostwa|Membership history/, { exact: true }).last();
        await history.focus();
        await history.press('Enter');
        await expect(page.getByText('Visibility Admin', { exact: true }).last()).toBeVisible();
    });
});
