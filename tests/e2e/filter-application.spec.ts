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

    await page.goto('/admin/work-time/summary');

    if (page.url().includes('/user/confirm-password')) {
        await page.getByLabel(/Hasło|Password/).fill('password');
        await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
    }
}

test.describe('Explicit filter application', () => {
    test('keeps result state unchanged until work-time filters are applied', async ({ page }) => {
        await signInAsAdmin(page);

        const selectTeamState = page.getByText(/Wybierz zespół, aby załadować rekordy|Select a team to load records/);
        await expect(selectTeamState).toBeVisible();

        await page
            .getByLabel(/Zespół|Team/)
            .first()
            .click();
        await page.getByRole('option', { name: /E2E Visibility Team/ }).click();

        await expect(selectTeamState).toBeVisible();
        await expect(page).not.toHaveURL(/[?&]team=/);

        await Promise.all([
            page.waitForResponse((response) => {
                const url = new URL(response.url());

                return response.request().method() === 'GET' && url.pathname === '/admin/work-time/summary' && url.searchParams.has('team');
            }),
            page.getByRole('button', { name: /Zastosuj|Apply/ }).click(),
        ]);

        await expect(selectTeamState).toHaveCount(0);
        await expect(page.getByRole('row').filter({ hasText: 'E2E Visibility Team' })).toBeVisible();

        const selectedTeam = new URL(page.url()).searchParams.get('team');
        const workSessionsLink = page.getByRole('link', { name: /Sesje pracy|Work sessions/ }).first();
        const workSessionsHref = await workSessionsLink.getAttribute('href');

        expect(selectedTeam).not.toBeNull();
        expect(new URL(workSessionsHref ?? '', page.url()).searchParams.get('team')).toBe(selectedTeam);

        await workSessionsLink.click();

        await expect(page).toHaveURL(/\/admin\/work-time\/work-sessions/);
        expect(new URL(page.url()).searchParams.get('team')).toBe(selectedTeam);
        await expect(page.getByRole('row').filter({ hasText: 'E2E Visibility Team' }).first()).toBeVisible();
    });
});
