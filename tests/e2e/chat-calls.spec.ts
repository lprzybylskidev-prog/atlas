import type { Page } from '@playwright/test';

import { expect, test } from './support/test';
import { completeSignIn } from './support/auth';

const teamName = 'E2E Visibility Team';

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click({ noWaitAfter: true });
    await completeSignIn(page, teamName);
}

function call(overrides: Record<string, unknown> = {}): Record<string, unknown> {
    return {
        publicId: '01CALL00000000000000000001',
        conversationPublicId: '01CONVERSATION00000000001',
        conversationType: 'direct',
        conversationLabel: 'Visibility User',
        startedByUserPublicId: '01USER0000000000000000001',
        startedByName: 'Visibility User',
        initialCameraEnabled: true,
        status: 'ringing',
        currentUserState: 'ringing',
        incoming: true,
        teamJoinStyle: false,
        canRejoin: true,
        startedAt: '2026-10-01T12:00:00+00:00',
        answeredAt: null,
        endedAt: null,
        participants: [],
        ...overrides,
    };
}

test('Call history and global Call states remain localized, explicit, and device-safe', async ({ page }) => {
    test.skip(test.info().project.name !== 'chromium', 'The media-device UI workflow is covered once in Chromium.');
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                enumerateDevices: async () => [],
                getUserMedia: async () => new MediaStream(),
            },
        });
    });
    await signIn(page);

    let currentCall: Record<string, unknown> | null = null;
    await page.route('**/chat/calls/current', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ call: currentCall }) });
    });
    await page.route('**/chat/call-preferences', async (route) => {
        await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                preferences: {
                    cameraDeviceId: null,
                    microphoneDeviceId: null,
                    speakerDeviceId: null,
                    outgoingCameraEnabled: false,
                },
            }),
        });
    });

    await page.goto('/user/calls');
    await expect(page.getByRole('heading', { name: /^(Historia połączeń|Call history)$/ })).toBeVisible();
    await expect(page.getByText(/Filtruj historię połączeń|Filter Call history/, { exact: true })).toBeVisible();
    await page
        .getByRole('navigation', { name: /Główna nawigacja|Main navigation/ })
        .getByText(/Moje sprawy|My matters/, { exact: true })
        .click();
    await expect(page.getByRole('link', { name: /^(Historia połączeń|Call history)$/ })).toBeVisible();

    currentCall = call();
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.getByRole('dialog', { name: /Połączenie przychodzące|Incoming Call/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Odrzuć|Decline/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Odbierz bez kamery|Answer without camera/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Odbierz z kamerą|Answer with camera/ })).toBeVisible();

    await page.getByRole('button', { name: /Odbierz bez kamery|Answer without camera/ }).click();
    await expect(page.getByTestId('call-preflight')).toBeVisible();
    await expect(page.getByRole('combobox', { name: /Kamera|Camera/ })).toBeVisible();
    await expect(page.getByRole('combobox', { name: /Mikrofon|Microphone/ })).toBeVisible();
    await expect(page.getByRole('combobox', { name: /Głośnik|Speaker/ })).toBeVisible();
    await expect(page.getByRole('checkbox', { name: /Kamera włączona|Camera enabled/ })).not.toBeChecked();

    currentCall = call({
        conversationType: 'team',
        conversationLabel: teamName,
        status: 'active',
        currentUserState: 'notified',
        incoming: false,
        teamJoinStyle: true,
    });
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.getByTestId('team-call-available')).toBeVisible();
    await expect(page.getByRole('dialog')).toHaveCount(0);
    await expect(page.getByText(/Połączenia Zespołu używają cichego powiadomienia|Team Calls use a quiet join notification/)).toBeVisible();

    currentCall = call({ status: 'active', currentUserState: 'left', incoming: false, canRejoin: true });
    await page.waitForLoadState('networkidle');
    await page.reload();
    await expect(page.getByRole('dialog', { name: /Dołącz ponownie do połączenia|Rejoin Call/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Dołącz bez kamery|Join without camera/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Dołącz z kamerą|Join with camera/ })).toBeVisible();
});
