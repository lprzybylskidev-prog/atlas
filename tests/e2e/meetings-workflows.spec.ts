import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';
import type { Page } from '@playwright/test';

async function signInToVisibilityTeam(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(email);
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
}

async function mockBroadcastingAuthorization(page: Page): Promise<void> {
    await page.route('**/broadcasting/auth', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ auth: 'e2e:signature' }) });
    });
}

test('opens shared device preparation for an online Meeting and releases media on close', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'The media-device Meeting workflow is covered once in Chromium.');
    await page.route('**/broadcasting/auth', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ auth: 'e2e:signature' }) });
    });
    await page.addInitScript(() => {
        Object.defineProperty(window, '__atlasStoppedMediaTracks', { configurable: true, value: 0, writable: true });
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                enumerateDevices: async () => [
                    { deviceId: 'camera-e2e', groupId: 'video', kind: 'videoinput', label: 'E2E camera', toJSON: () => ({}) },
                    { deviceId: 'microphone-e2e', groupId: 'audio', kind: 'audioinput', label: 'E2E microphone', toJSON: () => ({}) },
                    { deviceId: 'speaker-e2e', groupId: 'audio', kind: 'audiooutput', label: 'E2E speaker', toJSON: () => ({}) },
                ],
                getUserMedia: async () => {
                    const stream = new MediaStream();
                    Object.defineProperty(stream, 'getTracks', {
                        value: () => [
                            {
                                stop: () => {
                                    const target = window as unknown as { __atlasStoppedMediaTracks: number };
                                    target.__atlasStoppedMediaTracks += 1;
                                },
                            },
                        ],
                    });

                    return stream;
                },
            },
        });
    });

    const title = `E2E online device check ${testInfo.project.name}`;
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
    await page.goto('/meetings');
    await page.getByRole('button', { name: /Spotkajmy się teraz|Meet now/ }).click();
    await page.getByLabel(/Tytuł|Title/).fill(title);
    await page.getByRole('button', { name: /Utwórz spotkanie|Create Meeting/ }).click();

    await page.getByRole('button', { name: /Dołącz online|Join online/ }).click();
    const dialog = page.getByRole('dialog', { name: /Sprawdź urządzenia przed spotkaniem|Check devices before the Meeting/ });
    await expect(dialog).toBeVisible();
    await expect(page.getByTestId('meeting-preflight')).toBeVisible();
    await expect(dialog.getByRole('combobox', { name: /Kamera|Camera/ })).toBeVisible();
    await expect(dialog.getByRole('combobox', { name: /Mikrofon|Microphone/ })).toBeVisible();
    await expect(dialog.getByRole('combobox', { name: /Głośnik|Speaker/ })).toBeVisible();
    await dialog.getByRole('checkbox', { name: /Kamera włączona|Camera enabled/ }).check();
    await expect(dialog.locator('video')).toBeVisible();
    await dialog
        .getByRole('button', { name: /Zamknij|Close/, exact: true })
        .last()
        .click();
    await expect(dialog).toHaveCount(0);
    await expect
        .poll(() => page.evaluate(() => (window as unknown as { __atlasStoppedMediaTracks: number }).__atlasStoppedMediaTracks))
        .toBeGreaterThan(0);
    await page.waitForLoadState('networkidle');
});

test('creates an in-person Meeting with pending invitation and Calendar presentation', async ({ page }, testInfo) => {
    const title = `E2E in-person planning ${testInfo.project.name}`;
    const location = `Room E2E-${testInfo.project.name}`;

    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill('admin@example.test');
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();
    await completeSignIn(page, 'E2E Visibility Team');
    await page.waitForLoadState('networkidle');

    await page
        .getByText(/Pulpity|Dashboards/, { exact: true })
        .first()
        .click();
    await page
        .getByRole('link', { name: /Spotkania|Meetings/, exact: true })
        .first()
        .click();
    await expect(page.getByRole('heading', { level: 1, name: /Spotkania|Meetings/ })).toBeVisible();
    await page.getByRole('button', { name: /Zaplanuj spotkanie|Schedule Meeting/ }).click();
    await page.getByLabel(/Tytuł|Title/).fill(title);
    await page.getByRole('combobox', { name: /Tryb spotkania|Meeting mode/ }).click();
    await page.getByRole('option', { name: /Stacjonarne|In person/ }).click();
    await page.getByLabel(/Miejsce fizyczne|Physical location/).fill(location);
    await page.getByLabel(/Początek|Starts at/).fill('2026-10-20');
    await page.getByLabel(/Koniec|Ends at/).fill('2026-10-20');
    await page
        .getByLabel(/Godz.|Hour/)
        .first()
        .fill('09');
    await page
        .getByLabel(/Godz.|Hour/)
        .nth(1)
        .fill('10');
    await page.getByRole('checkbox', { name: /Visibility User/ }).click();
    await page.getByRole('button', { name: /Utwórz spotkanie|Create Meeting/ }).click();

    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    await expect(page.getByText(/Stacjonarne|In person/).first()).toBeVisible();
    await expect(page.getByText(location)).toBeVisible();
    await expect(page.getByText(/Visibility User/)).toBeVisible();
    await expect(page.getByText(/Brak odpowiedzi|No response/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Dołącz online|Join online/ })).toHaveCount(0);
    await expect(page.getByRole('dialog', { name: /Sprawdź urządzenia przed spotkaniem|Check devices before the Meeting/ })).toHaveCount(0);
    await expect(page.getByTestId('meeting-preflight')).toHaveCount(0);

    await page.waitForLoadState('networkidle');
    await page
        .getByRole('link', { name: /Kalendarz|Calendar/, exact: true })
        .first()
        .click();
    await page.getByRole('button', { name: /^Agenda$/ }).click();
    await expect(page.getByTestId('calendar-agenda-view')).toBeVisible();
    await page.getByLabel(/Wybierz datę|Choose date/).fill('2026-10-20');
    await expect(page.getByText(title)).toBeVisible();
    await expect(page.getByText(/Stacjonarne|In person/).first()).toBeVisible();
    await expect(page.getByText(location)).toBeVisible();
});

test('keeps one hybrid Meeting visible and RTC-ready for organizer and invited participant in separate browsers', async ({
    page,
    browser,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'The two-browser Meeting acceptance workflow is covered once in Chromium.');
    const title = `E2E hybrid two-browser ${testInfo.project.name}`;
    await mockBroadcastingAuthorization(page);
    await signInToVisibilityTeam(page, 'admin@example.test');
    await page.goto('/meetings');
    await page.getByRole('button', { name: /Spotkajmy się teraz|Meet now/ }).click();
    await page.getByLabel(/Tytuł|Title/).fill(title);
    await page.getByRole('combobox', { name: /Tryb spotkania|Meeting mode/ }).click();
    await page.getByRole('option', { name: /Hybrydowe|Hybrid/ }).click();
    await page.getByLabel(/Miejsce fizyczne|Physical location/).fill('E2E hybrid room');
    await page.getByRole('checkbox', { name: /Visibility User/ }).click();
    await page.getByRole('button', { name: /Utwórz spotkanie|Create Meeting/ }).click();
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
    const meetingUrl = page.url();
    await expect(page.getByText('E2E hybrid room')).toBeVisible();
    await expect(page.getByRole('button', { name: /Dołącz online|Join online/ })).toBeVisible();

    const participantContext = await browser.newContext();
    const participantPage = await participantContext.newPage();
    try {
        await mockBroadcastingAuthorization(participantPage);
        await signInToVisibilityTeam(participantPage, 'limited@example.test');
        await participantPage.goto(meetingUrl);
        await expect(participantPage.getByRole('heading', { level: 1, name: title })).toBeVisible();
        await expect(participantPage.getByText('E2E hybrid room')).toBeVisible();
        await expect(participantPage.getByRole('button', { name: /Dołącz online|Join online/ })).toBeVisible();
        await participantPage.getByRole('button', { name: /Akceptuj|Accept/ }).click();
        await expect(participantPage.getByText(/Zaakceptowano|Accepted/).first()).toBeVisible();
        await participantPage.getByRole('button', { name: /Dołącz online|Join online/ }).click();
        await expect(participantPage.getByTestId('meeting-preflight')).toBeVisible();
    } finally {
        await participantContext.close();
    }
});
