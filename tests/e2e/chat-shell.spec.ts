import type { Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
}

test('Chat shell keeps separate unread controls and desktop/mobile accessible modal behavior', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'Responsive Chat shell acceptance is covered once in Chromium.');
    await signIn(page);
    await page.request.get('/chat/team-conversation');
    await page.reload();

    await expect(page.getByRole('button', { name: /Otwórz Czat|Open Chat/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /nieprzeczytane rozmowy|unread Chat conversations/i })).toBeVisible();
    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    const dialog = page.getByRole('dialog', { name: /Czat|Chat/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('tab', { name: /Spotkania|Meeting/ })).toBeVisible();
    await expect(dialog.getByText('E2E Visibility Team')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);

    await page.getByRole('button', { name: /ciemny motyw|dark theme/i }).click();
    await expect(page.locator('html')).toHaveClass(/dark/);

    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    await expect(dialog).toBeVisible();
    const bounds = await dialog.boundingBox();
    expect(bounds?.width).toBeGreaterThanOrEqual(389);
    expect(bounds?.height).toBeGreaterThanOrEqual(843);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: /Otwórz Czat|Open Chat/ })).toBeFocused();
});
