import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

type SignInDestination = 'dashboard' | 'session-conflict' | 'team-selection';

async function waitForSignInDestination(page: Page): Promise<SignInDestination> {
    const sessionConflict = page.locator('button:not([disabled])', {
        hasText: /Kontynuuj tutaj|Continue here/,
    });
    const teamSelection = page.getByRole('heading', { name: /Wybierz aktywny zespół|Choose active team/ });

    return Promise.race([
        page.waitForURL((url) => url.pathname === '/').then(() => 'dashboard' as const),
        sessionConflict.waitFor({ state: 'visible' }).then(() => 'session-conflict' as const),
        teamSelection.waitFor({ state: 'visible' }).then(() => 'team-selection' as const),
    ]);
}

export async function completeSignIn(page: Page, preferredTeamName?: string): Promise<void> {
    for (let step = 0; step < 3; step += 1) {
        const destination = await waitForSignInDestination(page);

        if (destination === 'dashboard') {
            return;
        }

        if (destination === 'session-conflict') {
            const continueHere = page.locator('button:not([disabled])', {
                hasText: /Kontynuuj tutaj|Continue here/,
            });
            await continueHere.click({ noWaitAfter: true });
            continue;
        }

        const teamSelect = page.getByRole('combobox', { name: /Zespół|Team/ });
        await teamSelect.click();

        if (preferredTeamName !== undefined) {
            await page.getByRole('option', { name: preferredTeamName, exact: true }).click();
            await expect(teamSelect).toContainText(preferredTeamName);
        } else {
            await page.getByRole('option').first().click();
        }

        const continueWithTeam = page.getByRole('button', {
            name: /Kontynuuj|Continue/,
            exact: true,
        });
        await expect(continueWithTeam).toBeEnabled();
        await Promise.all([page.waitForURL((url) => url.pathname === '/'), continueWithTeam.click({ noWaitAfter: true })]);
        return;
    }

    await expect(page).toHaveURL('/');
}
