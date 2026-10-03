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
    await page.route('**/chat/search?*', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                items: [
                    {
                        type: 'message',
                        publicId: '01MESSAGESEARCH00000000001',
                        title: 'E2E Visibility Team',
                        snippet: 'Searchable project decision',
                        conversationPublicId: '01CONVERSATIONSEARCH00001',
                        messagePublicId: '01MESSAGESEARCH00000000001',
                        transcriptionPublicId: null,
                        occurredAt: '2026-10-02T10:00:00+00:00',
                        authorName: 'Atlas Administrator',
                    },
                    {
                        type: 'transcript',
                        publicId: '01TRANSCRIPTSEARCH00000001',
                        title: '',
                        snippet: 'Shared transcript decision',
                        conversationPublicId: null,
                        messagePublicId: null,
                        transcriptionPublicId: '01TRANSCRIPTSEARCH00000001',
                        occurredAt: '2026-10-02T11:00:00+00:00',
                        authorName: null,
                    },
                ],
                estimatedTotal: 2,
            }),
        });
    });
    await signIn(page);
    await page.request.get('/chat/team-conversation');
    await page.reload();

    await expect(page.getByRole('button', { name: /Otwórz Czat|Open Chat/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /nieprzeczytane rozmowy|unread Chat conversations/i })).toBeVisible();
    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    const dialog = page.getByRole('dialog', { name: /Czat|Chat/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('tab', { name: /Spotkania|Meeting/ })).toBeVisible();
    await dialog.getByRole('button', { name: /E2E Visibility Team/ }).click();
    await expect(dialog.getByRole('button', { name: /Eksportuj konwersację|Export conversation/ })).toBeVisible();
    await expect(dialog.getByRole('combobox', { name: /Format eksportu|Export format/ })).toBeVisible();
    await dialog.getByRole('button', { name: /Przeszukaj Czat|Search Chat/ }).click();
    await expect(dialog.getByRole('textbox', { name: /^(Wyszukiwanie|Search)$/ })).toBeVisible();
    await dialog.getByText(/Filtry|Filters/, { exact: true }).click();
    await dialog.getByLabel(/Osoba lub autor|Person or author/).fill('Atlas Administrator');
    await dialog.getByRole('combobox', { name: /Typ|Type/ }).click();
    await dialog.getByRole('option', { name: /Wiadomość|Message/ }).click();
    await dialog.getByRole('textbox', { name: /^(Wyszukiwanie|Search)$/ }).fill('decision');
    const response = page.waitForResponse((candidate) => {
        const url = new URL(candidate.url());
        return (
            url.pathname === '/chat/search' &&
            url.searchParams.get('author') === 'Atlas Administrator' &&
            url.searchParams.get('type') === 'message'
        );
    });
    await dialog.getByRole('button', { name: /^(Szukaj|Search)$/ }).click();
    await response;
    await expect(dialog.getByText('Searchable project decision')).toBeVisible();
    await expect(dialog.locator('strong').filter({ hasText: /^(Udostępniona transkrypcja|Shared transcript)$/ })).toBeVisible();
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
