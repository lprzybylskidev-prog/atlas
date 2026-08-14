import type { Browser, BrowserContext, Page } from '@playwright/test';

import { expect, test } from './support/test';
import { completeSignIn } from './support/auth';

const teamName = 'E2E Visibility Team';

interface RealtimeState {
    connected: boolean;
    messages: { publicId: string; body: string | null }[];
    presence: { userPublicId: string; manualStatus: string }[];
    typingUserPublicIds: string[];
    stateEvents: Record<string, unknown>[];
    reconciliations: number;
    connectionState: string;
    connectionError: string | null;
}

async function login(browser: Browser, email: string): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email/).fill(email);
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click({ noWaitAfter: true });
    await completeSignIn(page, teamName);
    return { context, page };
}

async function json<T>(page: Page, url: string, method = 'GET', body?: unknown): Promise<T> {
    return page.evaluate(
        async ({ endpoint, requestMethod, requestBody }) => {
            const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));
            const token = cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
            const response = await fetch(endpoint, {
                method: requestMethod,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
                body: requestBody === undefined ? undefined : JSON.stringify(requestBody),
            });
            if (!response.ok) throw new Error(`Request failed with ${response.status}: ${await response.text()}`);
            return response.json() as Promise<T>;
        },
        { endpoint: url, requestMethod: method, requestBody: body },
    );
}

async function mount(page: Page, conversationPublicId: string, userPublicId: string): Promise<void> {
    await page.evaluate(
        async ({ conversation, user }) => {
            const modulePath = 'http://127.0.0.1:5174/resources/js/Testing/mountChatRealtime.ts';
            const module = await import(/* @vite-ignore */ modulePath);
            module.mountChatRealtime(conversation, user);
        },
        { conversation: conversationPublicId, user: userPublicId },
    );
}

async function state(page: Page): Promise<RealtimeState> {
    return page.evaluate(() => {
        const harness = (
            window as typeof window & {
                __chatRealtime?: { state: RealtimeState };
            }
        ).__chatRealtime;
        if (harness === undefined) throw new Error('Chat realtime harness is not mounted.');
        return harness.state;
    });
}

test('two browser contexts reconcile Reverb messages, presence, typing, delivery, read, and unread', async ({ browser }) => {
    test.setTimeout(90_000);
    test.skip(test.info().project.name !== 'chromium', 'The multi-context Reverb workflow is covered once in Chromium.');

    const author = await login(browser, 'admin@example.test');
    const member = await login(browser, 'limited@example.test');
    try {
        const authorPresence = await json<{ userPublicId: string }>(author.page, '/chat/realtime/heartbeat', 'POST');
        const memberPresence = await json<{ userPublicId: string }>(member.page, '/chat/realtime/heartbeat', 'POST');
        const authorConversation = await json<{ publicId: string }>(author.page, '/chat/team-conversation');
        const memberConversation = await json<{ publicId: string }>(member.page, '/chat/team-conversation');
        expect(memberConversation.publicId).toBe(authorConversation.publicId);

        await mount(author.page, authorConversation.publicId, authorPresence.userPublicId);
        await mount(member.page, memberConversation.publicId, memberPresence.userPublicId);
        await expect
            .poll(async () => {
                const current = await state(author.page);
                return `${current.connectionState}:${current.connectionError ?? ''}`;
            })
            .toBe('connected:');
        await expect.poll(async () => (await state(member.page)).connected).toBe(true);
        await expect.poll(async () => (await state(author.page)).presence.length).toBeGreaterThanOrEqual(2);

        const sent = await json<{ publicId: string }>(author.page, `/chat/conversations/${authorConversation.publicId}/messages`, 'POST', {
            body: 'Realtime across contexts',
            client_message_key: `e2e-realtime-${Date.now()}`,
        });
        await expect.poll(async () => (await state(member.page)).messages.some(({ publicId }) => publicId === sent.publicId)).toBe(true);

        await author.page.evaluate(() => {
            const harness = (window as typeof window & { __chatRealtime?: { client: { whisperTyping(value: boolean): void } } })
                .__chatRealtime;
            harness?.client.whisperTyping(true);
        });
        await expect.poll(async () => (await state(member.page)).typingUserPublicIds).toContain(authorPresence.userPublicId);
        await expect
            .poll(async () => (await state(member.page)).typingUserPublicIds, { timeout: 7_000 })
            .not.toContain(authorPresence.userPublicId);

        await member.page.evaluate((messagePublicId) => {
            const harness = (
                window as typeof window & {
                    __chatRealtime?: { client: { markRead(id: string): Promise<{ ok: true }> } };
                }
            ).__chatRealtime;
            return harness?.client.markRead(messagePublicId);
        }, sent.publicId);
        await expect
            .poll(async () =>
                (await state(author.page)).stateEvents.some(
                    (event) => event.userPublicId === memberPresence.userPublicId && event.lastReadMessagePublicId === sent.publicId,
                ),
            )
            .toBe(true);

        await member.page.evaluate((messagePublicId) => {
            const harness = (
                window as typeof window & {
                    __chatRealtime?: { client: { markUnread(id: string): Promise<{ ok: true }> } };
                }
            ).__chatRealtime;
            return harness?.client.markUnread(messagePublicId);
        }, sent.publicId);
        await expect.poll(async () => (await json<{ totalUnread: number }>(member.page, '/chat/unread')).totalUnread).toBe(1);

        await member.page.evaluate(() => {
            const harness = (window as typeof window & { __chatRealtime?: { client: { stop(): void } } }).__chatRealtime;
            harness?.client.stop();
        });
        const missed = await json<{ publicId: string }>(
            author.page,
            `/chat/conversations/${authorConversation.publicId}/messages`,
            'POST',
            { body: 'Missed while disconnected', client_message_key: `e2e-reconnect-${Date.now()}` },
        );
        await mount(member.page, memberConversation.publicId, memberPresence.userPublicId);
        await expect.poll(async () => (await state(member.page)).messages.some(({ publicId }) => publicId === missed.publicId)).toBe(true);
        await expect
            .poll(async () =>
                (await state(author.page)).stateEvents.some(
                    (event) => event.userPublicId === memberPresence.userPublicId && event.lastDeliveredMessagePublicId === missed.publicId,
                ),
            )
            .toBe(true);

        await json(member.page, '/chat/realtime/status', 'PATCH', {
            status: 'busy',
            custom_text: 'Reviewing realtime',
            custom_emoji: '🟠',
        });
        const reconciled = await json<{ presence: { userPublicId: string; manualStatus: string }[] }>(
            author.page,
            `/chat/conversations/${authorConversation.publicId}/realtime`,
        );
        expect(reconciled.presence).toContainEqual(
            expect.objectContaining({ userPublicId: memberPresence.userPublicId, manualStatus: 'busy' }),
        );

        const denied = await member.page.request.post('/broadcasting/auth', {
            form: { socket_id: '1234.5678', channel_name: 'presence-chat.conversation.01JZZZZZZZZZZZZZZZZZZZZZZZ' },
        });
        expect(denied.status()).toBe(403);
    } finally {
        await author.context.close();
        await member.context.close();
    }
});
