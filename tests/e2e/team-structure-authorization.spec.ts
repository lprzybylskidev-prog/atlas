import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

async function signInAsAdmin(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    if (
        !(await page
            .waitForURL('/', { timeout: 2000 })
            .then(() => true)
            .catch(() => false))
    ) {
        await page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ }).click();
        await expect(page).toHaveURL('/');
    }

    await page.goto('/admin/teams');

    if (page.url().includes('/user/confirm-password')) {
        await page.getByLabel(/Hasło|Password/).fill('password');
        await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
    }
}

test.describe('Integrated team structure and authorization workflow', () => {
    test('keeps manager structure inside Teams and exposes accessible repeatable assignments on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await signInAsAdmin(page);

        await expect(page.locator('a[href="/admin/managers"]')).toHaveCount(0);

        const teamRow = page.getByRole('row').filter({ hasText: 'E2E Visibility Team' });
        const editTeam = teamRow.getByRole('button', { name: /Edytuj|Edit/ });
        await expect(editTeam).toBeVisible();
        await editTeam.click();

        const structureLink = page.getByRole('button', { name: /Struktura zespołu|Team structure/ }).first();
        await expect(structureLink).toBeVisible();
        await structureLink.click();

        await expect(page).toHaveURL(/\/admin\/teams\/[0-9A-HJKMNP-TV-Z]{26}\/structure/);
        await expect(page.getByRole('heading', { name: /Struktura zespołu|Team structure/, exact: true }).first()).toBeVisible();

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
        await expect(existingManagerOption.getByText('Manager', { exact: true })).toBeVisible();
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

        await page.goBack();
        const assignmentDisclosure = page.locator('button[aria-controls^="authorization-assignment-"]').first();

        if ((await assignmentDisclosure.count()) > 0) {
            await expect(assignmentDisclosure).toHaveAttribute('aria-expanded', 'true');
            await assignmentDisclosure.press('Enter');
            await expect(assignmentDisclosure).toHaveAttribute('aria-expanded', 'false');
            await assignmentDisclosure.press('Enter');
            await expect(assignmentDisclosure).toHaveAttribute('aria-expanded', 'true');
        }
    });
});
