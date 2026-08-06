import type { Page, TestInfo } from '@playwright/test';

import { expect, test } from './support/test';

const admin = {
    email: 'admin@example.test',
    password: 'password',
};

async function signIn(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email').fill(admin.email);
    await page.getByLabel(/Hasło|Password/).fill(admin.password);
    await page.getByRole('button', { name: /Zaloguj|Log in/ }).click();

    if (
        await page
            .waitForURL('/', { timeout: 2000 })
            .then(() => true)
            .catch(() => false)
    ) {
        return;
    }

    await page.getByRole('button', { name: /Kontynuuj tutaj|Continue here/ }).click();
    await expect(page).toHaveURL('/');
}

async function confirmAdministratorAccess(page: Page): Promise<void> {
    await page.getByLabel(/Hasło|Password/).fill(admin.password);
    await page.getByRole('button', { name: /Potwierdź|Confirm/ }).click();
}

async function openAudit(page: Page): Promise<void> {
    await page.goto('/admin/audit');

    if (page.url().includes('/user/confirm-password')) {
        await confirmAdministratorAccess(page);
    }

    await expect(page.getByRole('heading', { name: /Audyt|Audit/ })).toBeVisible();
}

async function chooseSelect(page: Page, label: string | RegExp, option: string | RegExp): Promise<void> {
    for (let attempt = 0; attempt < 3; attempt += 1) {
        const combobox = page.getByRole('combobox', { name: label }).filter({ visible: true }).first();

        try {
            await expect(combobox).toBeVisible();
            await combobox.click();

            const listboxId = await combobox.getAttribute('aria-controls');

            if (listboxId !== null) {
                await page
                    .locator(`#${listboxId}`)
                    .getByRole('option', { name: option, exact: typeof option === 'string' })
                    .click();
                return;
            }

            await page
                .getByRole('option', { name: option, exact: typeof option === 'string' })
                .filter({ visible: true })
                .first()
                .click();
            return;
        } catch (error) {
            if (attempt === 2) {
                throw error;
            }

            await page.waitForLoadState('networkidle');
        }
    }
}

function readableOption(value: string): RegExp {
    const readable = value
        .split(/[-_\s.]+/u)
        .filter(Boolean)
        .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
        .join(' ');

    return new RegExp(`${escapeRegExp(value)}|${escapeRegExp(readable)}`);
}

function moduleOption(value: string): RegExp {
    if (value === 'identity') {
        return /Tożsamość|Identity/;
    }

    return readableOption(value);
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function dismissToasts(page: Page): Promise<void> {
    const closeButtons = page.getByRole('button', { name: /Zamknij komunikat|Close message/ });

    while ((await closeButtons.count()) > 0) {
        await closeButtons.first().click();
    }
}

async function waitForAuditReload(page: Page, predicate: (url: URL) => boolean = () => true): Promise<void> {
    await page.waitForResponse((response) => {
        const url = new URL(response.url());

        return response.request().method() === 'GET' && url.pathname === '/admin/audit' && response.status() === 200 && predicate(url);
    });
}

async function waitForMutation(page: Page, method: string, predicate: (url: URL) => boolean): Promise<void> {
    await page.waitForResponse((response) => {
        const url = new URL(response.url());

        return response.request().method() === method && response.status() < 400 && predicate(url);
    });
}

async function openViews(page: Page): Promise<void> {
    await dismissToasts(page);

    const savedViewName = page.getByLabel(/Nazwa widoku|Saved view name/).filter({ visible: true });
    const savedTableView = page.getByRole('combobox', { name: 'Saved table view' }).filter({ visible: true });
    const viewsDetails = page
        .locator('details')
        .filter({ hasText: /Widoki|Views/ })
        .first();
    const viewsSummary = page
        .locator('summary')
        .filter({ hasText: /Widoki|Views/ })
        .first();

    for (let attempt = 0; attempt < 5; attempt += 1) {
        if ((await savedViewName.isVisible()) && (await savedTableView.isVisible())) {
            return;
        }

        await expect(viewsSummary).toBeVisible();
        await viewsDetails.evaluate((details) => {
            if (details instanceof HTMLDetailsElement) {
                details.open = true;
            }
        });
        await page.waitForTimeout(100);
    }

    await expect(savedViewName).toBeVisible();
    await expect(savedTableView).toBeVisible();
}

async function fillSavedViewName(page: Page, name: string): Promise<void> {
    for (let attempt = 0; attempt < 5; attempt += 1) {
        await openViews(page);

        const input = page
            .getByLabel(/Nazwa widoku|Saved view name/)
            .filter({ visible: true })
            .first();

        try {
            await expect(input).toBeVisible();
            await input.fill(name, { timeout: 5000 });
            await expect(input).toHaveValue(name);

            return;
        } catch (error) {
            if (attempt === 4) {
                throw error;
            }

            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(150);
        }
    }
}

async function saveView(page: Page, name: string, type: 'private' | 'team' = 'private'): Promise<void> {
    await openViews(page);
    await fillSavedViewName(page, name);
    await openViews(page);
    await chooseSelect(
        page,
        /Typ widoku|Saved view type/,
        type === 'private' ? /Prywatny|Private/ : /Współdzielony z zespołem|Team shared/,
    );
    await openViews(page);
    const reload = waitForAuditReload(page);
    const mutation = waitForMutation(page, 'POST', (url) => url.pathname === '/admin/table-views');
    await page.getByRole('button', { name: /Zapisz|Save/ }).click();
    await mutation;
    await reload;
    await expect.poll(async () => savedViewNames(page)).toContain(name);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(100);
}

async function savedViewNames(page: Page): Promise<string[]> {
    return page.evaluate(() => {
        const state = window.history.state as { page?: { props?: { table?: { savedViews?: Array<{ name?: unknown }> } } } } | null;

        return state?.page?.props?.table?.savedViews?.map((view) => (typeof view.name === 'string' ? view.name : '')) ?? [];
    });
}

async function savedViewId(page: Page, name: string): Promise<string> {
    return page.evaluate((viewName) => {
        const state = window.history.state as {
            page?: { props?: { table?: { savedViews?: Array<{ name?: unknown; publicId?: unknown }> } } };
        } | null;
        const view = state?.page?.props?.table?.savedViews?.find((candidate) => candidate.name === viewName);

        return typeof view?.publicId === 'string' ? view.publicId : '';
    }, name);
}

async function updateCurrentView(page: Page): Promise<void> {
    await openViews(page);
    await expect(page.getByRole('button', { name: /Aktualizuj|Update/ })).toBeVisible();
    await dismissToasts(page);
    const reload = waitForAuditReload(page);
    const mutation = waitForMutation(page, 'PATCH', (url) => url.pathname.startsWith('/admin/table-views/'));
    await page.getByRole('button', { name: /Aktualizuj|Update/ }).click();
    await mutation;
    await reload;
    await page.waitForLoadState('networkidle');
}

async function selectView(page: Page, name: string): Promise<void> {
    await openViews(page);
    const viewId = await savedViewId(page, name);
    const reload = waitForAuditReload(page, (url) => url.searchParams.get('view') === viewId);
    await chooseSelect(page, 'Saved table view', name);
    await reload;
    await expect(page).toHaveURL(/view=/);
    await expect.poll(() => page.evaluate(() => window.sessionStorage.getItem('atlas.table.admin.audit.selectedView'))).not.toBeNull();
    await page.waitForTimeout(350);
}

async function applyAuditFilters(
    page: Page,
    filters: { module: string; action: string; source?: string; security?: string },
): Promise<void> {
    await chooseSelect(page, /Moduł|Module/, moduleOption(filters.module));
    await chooseSelect(page, /Akcja|Action/, readableOption(filters.action));

    if (filters.source !== undefined) {
        await chooseSelect(page, /Źródło|Source/, readableOption(filters.source));
    }

    if (filters.security !== undefined) {
        await chooseSelect(
            page,
            /Typ audytu|Audit type|Security filter/,
            filters.security === 'yes' ? 'Security' : /Operacyjne|Operational/,
        );
    }

    const reload = waitForAuditReload(
        page,
        (url) =>
            url.searchParams.get('module') === filters.module &&
            url.searchParams.get('action') === filters.action &&
            (filters.source === undefined || url.searchParams.get('source') === filters.source) &&
            (filters.security === undefined || url.searchParams.get('security') === filters.security),
    );
    await page.getByRole('button', { name: /Zastosuj|Apply/ }).click();
    await reload;
    await page.waitForTimeout(350);
}

async function expectAuditFilterState(
    page: Page,
    expected: { module?: string; action?: string; source?: string; security?: string },
): Promise<void> {
    if (expected.module !== undefined) {
        await expect(page.getByRole('combobox', { name: /Moduł|Module/ })).toContainText(moduleOption(expected.module));
    }

    if (expected.action !== undefined) {
        await expect(page.getByRole('combobox', { name: /Akcja|Action/ })).toContainText(readableOption(expected.action));
    }

    if (expected.source !== undefined) {
        await expect(page.getByRole('combobox', { name: /Źródło|Source/ })).toContainText(readableOption(expected.source));
    }

    if (expected.security !== undefined) {
        await expect(page.getByRole('combobox', { name: /Typ audytu|Audit type|Security filter/ })).toContainText(
            expected.security === 'yes' ? 'Security' : /Operacyjne|Operational/,
        );
    }
}

test.describe('Audit DataTable saved views', () => {
    test('save, update, switch, and copy preserve audit filters', async ({ page }, testInfo: TestInfo) => {
        test.setTimeout(90000);

        const viewPrefix = `E2E ${testInfo.project.name} ${Date.now()}`;
        const firstView = `${viewPrefix} 1`;
        const secondView = `${viewPrefix} 2`;
        const copiedView = `${viewPrefix} 1 Copy`;

        await signIn(page);
        await openAudit(page);
        await expect(page.getByRole('textbox', { name: /Data od|From date/ })).toHaveAttribute('type', 'text');
        await expect(page.getByRole('textbox', { name: /Data do|To date/ })).toHaveAttribute('type', 'text');

        await applyAuditFilters(page, {
            module: 'identity',
            action: 'e2e.audit.alpha',
            source: 'e2e',
            security: 'yes',
        });
        await expect(page).toHaveURL(/module=identity/);
        await expect(page).toHaveURL(/action=e2e\.audit\.alpha/);
        await expect(page.getByRole('cell', { name: readableOption('e2e.audit.alpha') })).toBeVisible();
        await saveView(page, firstView);

        const clearReload = waitForAuditReload(page, (url) => !url.searchParams.has('module') || url.searchParams.get('module') === '');
        await page.getByRole('button', { name: /Wyczyść|Clear/, exact: true }).click();
        await clearReload;
        await expect.poll(() => new URL(page.url()).searchParams.get('module')).toBeNull();
        await saveView(page, secondView, 'team');

        await selectView(page, firstView);
        await expectAuditFilterState(page, {
            module: 'identity',
            action: 'e2e.audit.alpha',
            source: 'e2e',
            security: 'yes',
        });
        await expect(page).toHaveURL(/module=identity/);
        await expect(page.getByRole('cell', { name: readableOption('e2e.audit.alpha') })).toBeVisible();

        await applyAuditFilters(page, {
            module: 'shared',
            action: 'e2e.audit.beta',
            source: 'admin-ui',
            security: 'no',
        });
        await expect(page).toHaveURL(/view=/);
        await updateCurrentView(page);

        await expect(page).toHaveURL(/module=shared/);
        await expect(page).toHaveURL(/action=e2e\.audit\.beta/);
        await expect(page.getByRole('cell', { name: readableOption('e2e.audit.beta') })).toBeVisible();

        await openViews(page);
        await fillSavedViewName(page, copiedView);
        const copyReload = waitForAuditReload(page);
        const copyMutation = waitForMutation(
            page,
            'POST',
            (url) => url.pathname.startsWith('/admin/table-views/') && url.pathname.endsWith('/copy'),
        );
        await page.getByRole('button', { name: /Kopiuj|Copy/ }).click();
        await copyMutation;
        await copyReload;
        await expect.poll(async () => savedViewNames(page)).toContain(copiedView);
        await selectView(page, copiedView);
        await expectAuditFilterState(page, { module: 'shared', action: 'e2e.audit.beta' });
    });
});
