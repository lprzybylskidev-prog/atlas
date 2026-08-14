import type { Page } from '@playwright/test';

import { expect, test } from './support/test';
import { completeSignIn } from './support/auth';

const teamName = 'E2E Visibility Team';

async function login(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email/).fill(email);
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click({ noWaitAfter: true });
    await completeSignIn(page, teamName);
}

async function getJson<T>(page: Page, url: string): Promise<T> {
    const response = await page.request.get(url, { headers: { Accept: 'application/json' } });
    expect(response.ok(), await response.text()).toBe(true);
    return response.json() as Promise<T>;
}

test('picker, drop, clipboard, scan retry, and explicit voice preview/send work in a browser', async ({ page }) => {
    test.setTimeout(60_000);
    test.skip(test.info().project.name !== 'chromium', 'MediaRecorder interaction is covered once in Chromium.');

    await login(page, 'admin@example.test');
    const conversation = await getJson<{ publicId: string }>(page, '/chat/team-conversation');

    await page.evaluate(async (conversationPublicId) => {
        const modulePath = 'http://127.0.0.1:5174/resources/js/Testing/mountChatAttachments.ts';
        const module = await import(/* @vite-ignore */ modulePath);
        await module.mountChatAttachmentComposer(conversationPublicId);
    }, conversation.publicId);

    const input = page.locator('input[type="file"]');
    await input.setInputFiles({ name: 'picker.txt', mimeType: 'text/plain', buffer: Buffer.from('picker') });
    await expect(page.getByText('picker.txt')).toBeVisible();
    await expect(page.getByText(/Oczekuje na skanowanie|Awaiting scan/).first()).toBeVisible();

    await page
        .getByRole('button', { name: /Ponów skanowanie|Retry scan/ })
        .first()
        .click();
    await expect(page.getByText(/Gotowy|Ready/).first()).toBeVisible();

    const dropZone = page.getByRole('group', { name: /Dodaj załączniki|Add attachments/ });
    await dropZone.evaluate((element) => {
        const transfer = new DataTransfer();
        transfer.items.add(new File(['drop'], 'drop.txt', { type: 'text/plain' }));
        element.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: transfer }));
    });
    await expect(page.getByText('drop.txt')).toBeVisible();

    await dropZone.evaluate((element) => {
        const transfer = new DataTransfer();
        transfer.items.add(new File(['clipboard'], 'clipboard.txt', { type: 'text/plain' }));
        element.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: transfer }));
    });
    await expect(page.getByText('clipboard.txt')).toBeVisible();
    await expect(page.getByText(/Oczekuje na skanowanie|Awaiting scan/)).toHaveCount(2);

    const sendResponse = page.waitForResponse((response) =>
        response.url().endsWith(`/chat/conversations/${conversation.publicId}/messages`),
    );
    await page.getByRole('button', { name: /Wyślij załączniki|Send attachments/ }).click();
    const sent = await sendResponse;
    expect(sent.ok(), await sent.text()).toBe(true);
    await expect(page.getByRole('button', { name: /Wyślij załączniki|Send attachments/ })).toHaveCount(0);

    await page.evaluate(() => {
        let permissionRequests = 0;
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: async () => {
                    permissionRequests += 1;
                    (window as typeof window & { __microphoneRequests?: () => number }).__microphoneRequests = () => permissionRequests;
                    return { getTracks: () => [{ stop: () => undefined }] } as unknown as MediaStream;
                },
            },
        });
        class FakeMediaRecorder extends EventTarget {
            state: RecordingState = 'inactive';
            mimeType = 'audio/wav';
            constructor(public stream: MediaStream) {
                super();
            }
            start() {
                this.state = 'recording';
            }
            stop() {
                this.state = 'inactive';
                const wav = new Uint8Array([
                    0x52, 0x49, 0x46, 0x46, 0x25, 0x00, 0x00, 0x00, 0x57, 0x41, 0x56, 0x45, 0x66, 0x6d, 0x74, 0x20, 0x10, 0x00, 0x00, 0x00,
                    0x01, 0x00, 0x01, 0x00, 0x40, 0x1f, 0x00, 0x00, 0x80, 0x3e, 0x00, 0x00, 0x02, 0x00, 0x10, 0x00, 0x64, 0x61, 0x74, 0x61,
                    0x01, 0x00, 0x00, 0x00, 0x00,
                ]);
                this.dispatchEvent(new BlobEvent('dataavailable', { data: new Blob([wav], { type: this.mimeType }) }));
                this.dispatchEvent(new Event('stop'));
            }
        }
        Object.defineProperty(window, 'MediaRecorder', { configurable: true, value: FakeMediaRecorder });
    });

    await expect
        .poll(() => page.evaluate(() => Boolean((window as typeof window & { __microphoneRequests?: () => number }).__microphoneRequests)))
        .toBe(false);
    await page.getByRole('button', { name: /Nagraj wiadomość głosową|Record voice message/ }).click();
    await page.waitForTimeout(1100);
    await page.getByRole('button', { name: /Zatrzymaj nagrywanie|Stop recording/ }).click();
    await expect(page.getByLabel(/Odsłuch wiadomości głosowej|Voice message preview/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Wyślij wiadomość głosową|Send voice message/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Nagraj ponownie|Record again/ })).toBeVisible();
    await expect(page.getByRole('button', { name: /Odrzuć|Discard/ })).toBeVisible();
    await page.getByRole('button', { name: /Wyślij wiadomość głosową|Send voice message/ }).click();
    await expect(page.getByText('voice-message.wav')).toBeVisible();
});
