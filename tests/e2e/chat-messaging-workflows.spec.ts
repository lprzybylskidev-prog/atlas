import type { Browser, BrowserContext, Page } from '@playwright/test';

import { completeSignIn } from './support/auth';
import { expect, test } from './support/test';

const teamName = 'E2E Visibility Team';

async function signIn(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/Adres e-mail|Email address/).fill(email);
    await page.getByLabel(/Hasło|Password/).fill('password');
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click({ noWaitAfter: true });
    await completeSignIn(page, teamName);
}

async function json<T>(page: Page, url: string, method = 'GET', body?: unknown): Promise<T> {
    return page.evaluate(
        async ({ endpoint, requestMethod, payload }) => {
            const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));
            const response = await fetch(endpoint, {
                method: requestMethod,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '',
                },
                body: payload === undefined ? undefined : JSON.stringify(payload),
            });
            if (!response.ok) throw new Error(`Request ${requestMethod} ${endpoint} failed with ${response.status}.`);
            return response.json() as Promise<T>;
        },
        { endpoint: url, requestMethod: method, payload: body },
    );
}

async function participant(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await signIn(page, 'limited@example.test');
    return { context, page };
}

test('DM and Team messaging expose cursor history, replies, edits, reactions, pins, bookmarks, forwarding, deletion, and drafts', async ({
    page,
    browser,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium', 'The full two-user mutation matrix runs once in Chromium.');
    test.setTimeout(90_000);
    await signIn(page, 'admin@example.test');
    const users = await json<{ users: { publicId: string; name: string }[] }>(page, '/chat/group-candidates');
    const limited = users.users.find((user) => user.name === 'Visibility User');
    expect(limited).toBeDefined();
    const direct = await json<{ publicId: string }>(page, '/chat/direct-conversations', 'POST', {
        target_user_public_id: limited!.publicId,
    });

    for (let sequence = 1; sequence <= 51; sequence += 1) {
        await json(page, `/chat/conversations/${direct.publicId}/messages`, 'POST', {
            body: `Cursor fixture ${sequence}`,
            client_message_key: `e2e-cursor-${testInfo.project.name}-${sequence}`,
            mentioned_user_public_ids: sequence === 51 ? [limited!.publicId] : [],
        });
    }

    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    const chat = page.getByRole('dialog', { name: /Czat|Chat/ });
    await chat.getByRole('button', { name: /Visibility User/ }).click();
    await expect(chat.getByRole('button', { name: /Wczytaj wcześniejsze wiadomości|Load earlier messages/ })).toBeVisible();
    await chat.getByRole('button', { name: /Wczytaj wcześniejsze wiadomości|Load earlier messages/ }).click();
    await expect(chat.getByText('Cursor fixture 1', { exact: true })).toBeVisible();

    await chat.getByLabel(/Wiadomość|Message/).fill('Editable browser message');
    await chat.getByRole('button', { name: /Wyślij|Send/, exact: true }).click();
    const article = chat.locator('article').filter({ hasText: 'Editable browser message' });
    await expect(article).toBeVisible();
    await article.getByRole('button', { name: /Odpowiedz|Reply/ }).click();
    await chat.getByLabel(/Wiadomość|Message/).fill('Browser reply');
    await chat.getByRole('button', { name: /Wyślij|Send/, exact: true }).click();
    await expect(chat.getByText('Browser reply')).toBeVisible();

    await article.getByRole('button', { name: /Edytuj wiadomość|Edit message/ }).click();
    await chat.getByLabel(/Wiadomość|Message/).fill('Edited browser message');
    await chat.getByRole('button', { name: /Wyślij|Send/, exact: true }).click();
    const edited = chat.locator('article').filter({ hasText: 'Edited browser message' });
    await expect(edited.getByText(/^(Edytowano|Edited)$/)).toBeVisible();
    await edited.getByRole('button', { name: /Historia edycji|Edit history/ }).click();
    await expect(edited.getByText('Editable browser message')).toBeVisible();
    await edited.getByRole('button', { name: '👍' }).click();
    await edited.getByRole('button', { name: /Przypnij wiadomość|Pin message/ }).click();
    await expect(edited.getByText(/Przypięta|Pinned/)).toBeVisible();
    await edited.getByRole('button', { name: /Dodaj do zakładek|Bookmark message/ }).click();
    await expect(edited.getByText(/W zakładkach|Bookmarked/)).toBeVisible();

    const team = await json<{ publicId: string }>(page, '/chat/team-conversation');
    await edited.getByRole('combobox', { name: /Przekaż do rozmowy|Forward to conversation/ }).click();
    await page.getByRole('option', { name: teamName }).click();
    await edited.getByRole('button', { name: /Przekaż|Forward/, exact: true }).click();
    await edited.getByRole('button', { name: /Usuń dla mnie|Delete for me/ }).click();
    await expect(edited.getByText(/Wiadomość usunięta dla Ciebie|Message deleted for you/)).toBeVisible();

    await chat.getByLabel(/Wiadomość|Message/).fill('Persisted draft');
    await page.waitForTimeout(700);
    await chat
        .getByRole('button', { name: /Zamknij|Close/ })
        .last()
        .click();
    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    await chat.getByRole('button', { name: /Visibility User/ }).click();
    await expect(chat.getByLabel(/Wiadomość|Message/)).toHaveValue('Persisted draft');

    const second = await participant(browser);
    try {
        const received = await json<{ messages: { publicId: string; body: string | null }[] }>(
            second.page,
            `/chat/conversations/${team.publicId}/realtime`,
        );
        expect(received.messages.some((message) => message.body === 'Edited browser message')).toBe(true);
        const latestTeamMessage = received.messages.at(-1);
        if (latestTeamMessage !== undefined) {
            await json(second.page, `/chat/conversations/${team.publicId}/messages/${latestTeamMessage.publicId}/read`, 'POST');
        }
        const directMessages = await json<{ messages: { publicId: string }[] }>(
            second.page,
            `/chat/conversations/${direct.publicId}/realtime`,
        );
        const latestDirectMessage = directMessages.messages.at(-1);
        if (latestDirectMessage !== undefined) {
            await json(second.page, `/chat/conversations/${direct.publicId}/messages/${latestDirectMessage.publicId}/read`, 'POST');
        }
    } finally {
        await second.context.close();
    }
    await json(page, `/chat/conversations/${direct.publicId}/draft`, 'PUT', { body: '', reply_to_message_public_id: null });
});

test('group lifecycle and browser-native notification preference work through the rendered Chat shell', async ({
    page,
    browser,
}, testInfo) => {
    test.setTimeout(90_000);
    test.skip(testInfo.project.name !== 'chromium', 'Browser Notification and group composition run once in Chromium.');
    await page.addInitScript(() => {
        class FakeNotification {
            static permission: NotificationPermission = 'granted';
            static requestPermission = async (): Promise<NotificationPermission> => 'granted';
            constructor(title: string) {
                const target = window as unknown as { __atlasNotifications?: string[] };
                target.__atlasNotifications = [...(target.__atlasNotifications ?? []), title];
            }
        }
        Object.defineProperty(window, 'Notification', { configurable: true, value: FakeNotification });
    });
    await signIn(page, 'admin@example.test');
    await page.getByRole('button', { name: /Otwórz nieprzeczytane rozmowy|Open unread Chat conversations/ }).click();
    await page.getByRole('button', { name: /Włącz alerty Czatu|Enable Chat browser alerts/ }).click();
    await page.getByRole('button', { name: /Otwórz Czat|Open Chat/ }).click();
    const chat = page.getByRole('dialog', { name: /Czat|Chat/ });
    await chat.getByRole('button', { name: /Utwórz grupę|Create group/ }).click();
    await chat.getByLabel(/Nazwa grupy|Group name/).fill(`Browser group ${testInfo.project.name}`);
    await chat.getByRole('checkbox', { name: 'Visibility User' }).click();
    await chat
        .getByRole('button', { name: /Utwórz grupę|Create group/, exact: true })
        .last()
        .click();
    await expect(chat.getByText(`Browser group ${testInfo.project.name}`).first()).toBeVisible();
    await chat.getByText(/Zarządzaj grupą|Manage group/).click();
    await expect(chat.getByText('Visibility User', { exact: true }).last()).toBeVisible();
    const renamedGroup = `Renamed browser group ${testInfo.project.name}`;
    await chat.getByLabel(/Nazwa grupy|Group name/).fill(renamedGroup);
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/group') && response.ok()),
        chat.getByRole('button', { name: /Zapisz|Save/, exact: true }).click(),
    ]);
    await expect(chat.getByRole('heading', { name: renamedGroup })).toBeVisible();
    const groupManagement = chat.locator('details').filter({ hasText: /Zarządzaj grupą|Manage group/ });
    const openGroupManagement = async (): Promise<void> => {
        if (!(await groupManagement.evaluate((element: HTMLDetailsElement) => element.open))) {
            await groupManagement.locator('summary').click();
        }
    };
    const addMember = chat.getByRole('combobox', { name: /Dodaj uczestnika|Add member/ });
    await openGroupManagement();
    await expect(addMember).toBeVisible();
    await addMember.click();
    await page.getByRole('option', { name: 'Structure Head' }).click();
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/group') && response.ok()),
        chat.getByRole('button', { name: /Dodaj uczestnika|Add member/, exact: true }).click(),
    ]);
    await openGroupManagement();
    const structureMember = groupManagement
        .getByText(/^Structure Head Manager$/)
        .first()
        .locator('..');
    await expect(structureMember).toBeVisible();
    await expect(groupManagement.getByRole('button', { name: /Usuń|Remove/ })).toHaveCount(2);
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/group') && response.ok()),
        structureMember.getByRole('button', { name: /Usuń|Remove/ }).click(),
    ]);
    await expect(groupManagement.getByRole('button', { name: /Usuń|Remove/ })).toHaveCount(1);
    await openGroupManagement();
    const visibilityMember = groupManagement.getByText('Visibility User', { exact: true }).locator('..');
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'GET' && response.url().includes('/group') && response.ok()),
        visibilityMember.getByRole('button', { name: /Przekaż własność|Transfer ownership/ }).click(),
    ]);
    await openGroupManagement();
    await expect(chat.getByText(/Właściciel|Owner/).last()).toBeVisible();
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'PATCH' && response.url().includes('/group') && response.ok()),
        chat.getByRole('button', { name: /Opuść grupę|Leave group/ }).click(),
    ]);
    await expect(chat.getByRole('heading', { name: renamedGroup })).toHaveCount(0);
    await chat
        .getByRole('button', { name: /Zamknij|Close/ })
        .first()
        .click();

    const second = await participant(browser);
    let notificationConversationPublicId = '';
    let notificationMessagePublicId = '';
    try {
        const direct = await json<{ publicId: string }>(second.page, '/chat/direct-conversations', 'POST', {
            target_user_public_id: (
                await json<{ users: { publicId: string; name: string }[] }>(second.page, '/chat/group-candidates')
            ).users.find((user) => user.name === 'Visibility Admin')!.publicId,
        });
        const notificationMessage = await json<{ publicId: string }>(
            second.page,
            `/chat/conversations/${direct.publicId}/messages`,
            'POST',
            {
                body: 'Native notification fixture',
                client_message_key: `native-notification-${testInfo.project.name}`,
            },
        );
        notificationConversationPublicId = direct.publicId;
        notificationMessagePublicId = notificationMessage.publicId;
    } finally {
        await second.context.close();
    }
    await page.reload();
    await expect
        .poll(() => page.evaluate(() => (window as unknown as { __atlasNotifications?: string[] }).__atlasNotifications ?? []))
        .not.toEqual([]);
    await json(page, `/chat/conversations/${notificationConversationPublicId}/messages/${notificationMessagePublicId}/read`, 'POST');
});
