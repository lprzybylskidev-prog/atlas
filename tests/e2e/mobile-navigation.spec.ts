import type { Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

const administrator = { email: 'admin@example.test', password: 'password' };

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(administrator.email);
    await page.getByLabel(/Hasło|Password/).fill(administrator.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    await completeSignIn(page);
}

async function enterAdmin(page: Page, path = '/admin'): Promise<void> {
    await page.goto(path);

    if (page.url().includes('/user/confirm-password')) {
        await page.getByLabel(/Hasło|Password/).fill(administrator.password);
        await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
        await page.waitForURL(/\/admin/);
        if (path !== '/admin') await page.goto(path);
    }
}

test.describe('mobile navigation parity and accessibility', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('traps focus, closes with Escape, restores the opener, and exposes active route-backed links', async ({ page }) => {
        await signIn(page);
        const opener = page.getByRole('button', { name: /Otwórz nawigację|Open navigation/ });

        await opener.focus();
        await opener.click();

        const drawer = page.getByTestId('mobile-navigation-drawer');
        await expect(drawer).toBeVisible();
        await expect(page.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
        await expect(page.getByRole('button', { name: /Zamknij nawigację|Close navigation/ }).last()).toBeFocused();

        await page.keyboard.press('Shift+Tab');
        await expect(drawer.locator(':focus')).toHaveCount(1);
        await page.keyboard.press('Escape');
        await expect(drawer).toHaveCount(0);
        await expect(opener).toBeFocused();

        await page.context().setOffline(true);
        await expect(page.getByTestId('shell-security-context')).toContainText(/Brak połączenia|Offline/);
        await page.context().setOffline(false);
    });

    test('shows the same accepted Admin work-time subnavigation on mobile and persistent security context', async ({ page }) => {
        await signIn(page);
        await enterAdmin(page, '/admin/work-time/summary');

        await expect(page.getByTestId('shell-security-context')).toContainText(/Aktywny zespół|Active team/);
        await expect(page.getByTestId('shell-security-context')).toContainText(/Tryb administratora|Admin mode/);
        await expect(page.getByTestId('shell-security-context')).toContainText(
            /Operacje wysokiego ryzyka wymagają potwierdzenia|High-risk operations require confirmation/,
        );

        await page.getByRole('button', { name: /Otwórz nawigację|Open navigation/ }).click();
        const subnavigation = page.getByTestId('mobile-subnavigation');

        await expect(subnavigation).toBeVisible();
        await expect(subnavigation.getByRole('link', { name: /Podsumowanie|Summary/ })).toHaveAttribute('aria-current', 'page');
        await expect(subnavigation.getByRole('link', { name: /Praca poza komputerem|Work outside the computer/ })).toBeVisible();
        await expect(subnavigation.getByRole('link', { name: /Przerwy|Breaks/ })).toBeVisible();
        await expect(subnavigation.getByRole('link', { name: /Korekty|Corrections/ })).toBeVisible();
        await expect(subnavigation.getByRole('link', { name: /Sesje pracy|Work sessions/ })).toBeVisible();
    });
});
