import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const adminUser = {
    email: 'admin@example.test',
    password: 'password',
};

const appUser = {
    email: 'limited@example.test',
    password: 'password',
};

async function stabilizeVisuals(page: Page): Promise<void> {
    await page.addStyleTag({
        content: `
            *, *::before, *::after {
                animation-duration: 0s !important;
                animation-delay: 0s !important;
                transition-duration: 0s !important;
                transition-delay: 0s !important;
                caret-color: transparent !important;
            }
        `,
    });
}

async function waitForFullscreenTransitionIdle(page: Page): Promise<void> {
    await expect(page.getByTestId('fullscreen-transition-loader')).toHaveCount(0);
}

async function ensureDarkTheme(page: Page): Promise<void> {
    if ((await page.locator('html').getAttribute('class'))?.split(/\s+/).includes('dark') === true) {
        return;
    }

    await page.getByRole('button', { name: /Włącz ciemny motyw|Enable dark theme/ }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
    await waitForFullscreenTransitionIdle(page);
}

async function ensureLightTheme(page: Page): Promise<void> {
    if ((await page.locator('html').getAttribute('class'))?.split(/\s+/).includes('dark') !== true) {
        return;
    }

    await page.getByRole('button', { name: /Włącz jasny motyw|Enable light theme/ }).click();
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    await waitForFullscreenTransitionIdle(page);
}

async function signIn(page: Page, user = appUser): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(user.email);
    await page.getByLabel(/Hasło|Password/).fill(user.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    if (
        await page
            .waitForURL('/', { timeout: 2000 })
            .then(() => true)
            .catch(() => false)
    ) {
        return;
    }

    await page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ }).click();
    await expect(page).toHaveURL('/');
}

async function confirmAdminPassword(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill(adminUser.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
    await expect(page).toHaveURL('/admin');
}

async function expectShellScreenshot(page: Page, name: string): Promise<void> {
    await waitForFullscreenTransitionIdle(page);
    await expect(page).toHaveScreenshot(name, { fullPage: true, maxDiffPixels: 500 });
}

test.describe('frontend theme coverage', () => {
    test('renders the login shell in light and dark themes', async ({ page }) => {
        await page.goto('/login');
        await stabilizeVisuals(page);
        await ensureLightTheme(page);

        await expect(page.getByRole('heading', { name: /Zaloguj się|Log in/ })).toBeVisible();
        await expectShellScreenshot(page, 'login-shell-light.png');

        await ensureDarkTheme(page);
        await expectShellScreenshot(page, 'login-shell-dark.png');
    });

    test('renders the application shell in light and dark themes', async ({ page }) => {
        await signIn(page);
        await stabilizeVisuals(page);
        await ensureLightTheme(page);

        await expect(page.getByRole('heading', { name: /Pulpit|Dashboard/ })).toBeVisible();
        await expectShellScreenshot(page, 'application-shell-light.png');

        await ensureDarkTheme(page);
        await expectShellScreenshot(page, 'application-shell-dark.png');
    });

    test('renders the admin shell in light and dark themes', async ({ page }) => {
        await signIn(page, adminUser);
        await page.goto('/admin');
        await confirmAdminPassword(page);
        await stabilizeVisuals(page);
        await ensureLightTheme(page);

        await expect(page.getByRole('main').getByRole('heading', { name: 'Admin dashboard' })).toBeVisible();
        await expectShellScreenshot(page, 'admin-shell-light.png');

        await ensureDarkTheme(page);
        await expectShellScreenshot(page, 'admin-shell-dark.png');
    });
});
