import type { Page } from '@playwright/test';

import { expect, test } from './support/test';

const demoUser = {
    email: 'atlas@example.test',
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

async function enableDarkTheme(page: Page): Promise<void> {
    await page.getByRole('button', { name: /Włącz ciemny motyw|Enable dark theme/ }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);
}

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(demoUser.email);
    await page.getByLabel(/Hasło|Password/).fill(demoUser.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await expect(page).toHaveURL('/');
}

async function expectShellScreenshot(page: Page, name: string): Promise<void> {
    await expect(page).toHaveScreenshot(name, { fullPage: true, maxDiffPixels: 500 });
}

test.describe('frontend theme coverage', () => {
    test('renders the login shell in light and dark themes', async ({ page }) => {
        await page.goto('/login');
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: /Zaloguj się|Log in/ })).toBeVisible();
        await expectShellScreenshot(page, 'login-shell-light.png');

        await enableDarkTheme(page);
        await expectShellScreenshot(page, 'login-shell-dark.png');
    });

    test('renders the application shell in light and dark themes', async ({ page }) => {
        await signIn(page);
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: /Pulpit|Dashboard/ })).toBeVisible();
        await expectShellScreenshot(page, 'application-shell-light.png');

        await enableDarkTheme(page);
        await expectShellScreenshot(page, 'application-shell-dark.png');
    });

    test('renders the admin shell in light and dark themes', async ({ page }) => {
        await signIn(page);
        await page.goto('/admin');
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expectShellScreenshot(page, 'admin-shell-light.png');

        await enableDarkTheme(page);
        await expectShellScreenshot(page, 'admin-shell-dark.png');
    });
});
