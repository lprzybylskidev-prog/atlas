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

test.describe('frontend theme coverage', () => {
    test('renders the login shell in light and dark themes', async ({ page }) => {
        await page.goto('/login');
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: /Zaloguj się|Log in/ })).toBeVisible();
        await expect(page).toHaveScreenshot('login-shell-light.png', { fullPage: true });

        await enableDarkTheme(page);
        await expect(page).toHaveScreenshot('login-shell-dark.png', { fullPage: true });
    });

    test('renders the application shell in light and dark themes', async ({ page }) => {
        await signIn(page);
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: /Pulpit|Dashboard/ })).toBeVisible();
        await expect(page).toHaveScreenshot('application-shell-light.png', { fullPage: true });

        await enableDarkTheme(page);
        await expect(page).toHaveScreenshot('application-shell-dark.png', { fullPage: true });
    });

    test('renders the admin shell in light and dark themes', async ({ page }) => {
        await signIn(page);
        await page.goto('/admin');
        await stabilizeVisuals(page);

        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expect(page).toHaveScreenshot('admin-shell-light.png', { fullPage: true });

        await enableDarkTheme(page);
        await expect(page).toHaveScreenshot('admin-shell-dark.png', { fullPage: true });
    });
});
