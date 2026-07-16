import type { Page } from '@playwright/test';

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

    await expect(page.getByRole('heading', { name: 'Audit' })).toBeVisible();
}

async function chooseSelect(page: Page, label: string, option: string): Promise<void> {
    await page.getByRole('combobox', { name: label }).filter({ visible: true }).click();
    await page.getByRole('option', { name: option, exact: true }).filter({ visible: true }).click();
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
    const savedViewName = page.getByLabel('Saved view name').filter({ visible: true });

    for (let attempt = 0; attempt < 2; attempt += 1) {
        if (await savedViewName.isVisible()) {
            return;
        }

        await page.locator('summary').filter({ hasText: 'Views' }).click();
    }

    await expect(savedViewName).toBeVisible();
}

async function saveView(page: Page, name: string, type: 'Private' | 'Team shared' = 'Private'): Promise<void> {
    await openViews(page);
    await page.getByLabel('Saved view name').filter({ visible: true }).fill(name);
    await openViews(page);
    await chooseSelect(page, 'Saved view type', type);
    await openViews(page);
    const reload = waitForAuditReload(page);
    const mutation = waitForMutation(page, 'POST', (url) => url.pathname === '/admin/table-views');
    await page.getByRole('button', { name: 'Save' }).click();
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
    await expect(page.getByRole('button', { name: 'Update' })).toBeVisible();
    const reload = waitForAuditReload(page);
    const mutation = waitForMutation(page, 'PATCH', (url) => url.pathname.startsWith('/admin/table-views/'));
    await page.getByRole('button', { name: 'Update' }).click();
    await mutation;
    await reload;
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
    await chooseSelect(page, 'Module', filters.module);
    await chooseSelect(page, 'Action', filters.action);

    if (filters.source !== undefined) {
        await chooseSelect(page, 'Source', filters.source);
    }

    if (filters.security !== undefined) {
        await chooseSelect(page, 'Security filter', filters.security);
    }

    const reload = waitForAuditReload(
        page,
        (url) =>
            url.searchParams.get('module') === filters.module &&
            url.searchParams.get('action') === filters.action &&
            (filters.source === undefined || url.searchParams.get('source') === filters.source) &&
            (filters.security === undefined || url.searchParams.get('security') === (filters.security === 'Security only' ? 'yes' : 'no')),
    );
    await page.getByRole('button', { name: 'Apply' }).click();
    await reload;
    await page.waitForTimeout(350);
}

async function expectAuditFilterState(
    page: Page,
    expected: { module?: string; action?: string; source?: string; security?: string },
): Promise<void> {
    if (expected.module !== undefined) {
        await expect(page.getByRole('combobox', { name: 'Module' })).toContainText(expected.module);
    }

    if (expected.action !== undefined) {
        await expect(page.getByRole('combobox', { name: 'Action' })).toContainText(expected.action);
    }

    if (expected.source !== undefined) {
        await expect(page.getByRole('combobox', { name: 'Source' })).toContainText(expected.source);
    }

    if (expected.security !== undefined) {
        await expect(page.getByRole('combobox', { name: 'Security filter' })).toContainText(expected.security);
    }
}

test.describe('Audit DataTable saved views', () => {
    test('save, update, switch, copy, default, and delete preserve audit filters', async ({ page }) => {
        await signIn(page);
        await openAudit(page);
        await expect(page.getByRole('textbox', { name: 'Date from' })).toHaveAttribute('type', 'text');
        await expect(page.getByRole('textbox', { name: 'Date to' })).toHaveAttribute('type', 'text');

        await applyAuditFilters(page, {
            module: 'identity',
            action: 'e2e.audit.alpha',
            source: 'e2e',
            security: 'Security only',
        });
        await expect(page).toHaveURL(/module=identity/);
        await expect(page).toHaveURL(/action=e2e\.audit\.alpha/);
        await expect(page.getByRole('cell', { name: 'e2e.audit.alpha' })).toBeVisible();
        await saveView(page, 'Test 1');

        const clearReload = waitForAuditReload(page, (url) => !url.searchParams.has('module') || url.searchParams.get('module') === '');
        await page.getByRole('button', { name: 'Clear', exact: true }).click();
        await clearReload;
        await expect.poll(() => new URL(page.url()).searchParams.get('module')).toBe('');
        await saveView(page, 'Test 2', 'Team shared');

        await selectView(page, 'Test 1');
        await expectAuditFilterState(page, {
            module: 'identity',
            action: 'e2e.audit.alpha',
            source: 'e2e',
            security: 'Security only',
        });
        await expect(page).toHaveURL(/module=identity/);
        await expect(page.getByRole('cell', { name: 'e2e.audit.alpha' })).toBeVisible();

        await applyAuditFilters(page, {
            module: 'shared',
            action: 'e2e.audit.beta',
            source: 'admin-ui',
            security: 'Application only',
        });
        await expect(page).toHaveURL(/view=/);
        await updateCurrentView(page);

        await selectView(page, 'Test 2');
        await expectAuditFilterState(page, {
            module: 'Any module',
            action: 'Any action',
            source: 'Any source',
            security: 'Any audit type',
        });
        await expect(page).not.toHaveURL(/module=/);

        await selectView(page, 'Test 1');
        await expectAuditFilterState(page, {
            module: 'shared',
            action: 'e2e.audit.beta',
            source: 'admin-ui',
            security: 'Application only',
        });
        await expect(page).toHaveURL(/module=shared/);
        await expect(page).toHaveURL(/action=e2e\.audit\.beta/);
        await expect(page.getByRole('cell', { name: 'e2e.audit.beta' })).toBeVisible();

        await openViews(page);
        await page.getByLabel('Saved view name').fill('Test 1 Copy');
        const copyReload = waitForAuditReload(page);
        const copyMutation = waitForMutation(
            page,
            'POST',
            (url) => url.pathname.startsWith('/admin/table-views/') && url.pathname.endsWith('/copy'),
        );
        await page.getByRole('button', { name: 'Copy' }).click();
        await copyMutation;
        await copyReload;
        await expect.poll(async () => savedViewNames(page)).toContain('Test 1 Copy');
        await selectView(page, 'Test 1 Copy');
        await expectAuditFilterState(page, { module: 'shared', action: 'e2e.audit.beta' });

        await openViews(page);
        const defaultReload = waitForAuditReload(page);
        const defaultMutation = waitForMutation(
            page,
            'POST',
            (url) => url.pathname.startsWith('/admin/table-views/') && url.pathname.endsWith('/default'),
        );
        await page.getByRole('button', { name: 'Default' }).click();
        await defaultMutation;
        await defaultReload;

        await openViews(page);
        const deleteReload = waitForAuditReload(page);
        const deleteMutation = waitForMutation(page, 'DELETE', (url) => url.pathname.startsWith('/admin/table-views/'));
        await page.getByRole('button', { name: 'Delete' }).click();
        await deleteMutation;
        await deleteReload;
        await openViews(page);
        await expect(page.getByRole('option', { name: 'Test 1 Copy', exact: true })).toHaveCount(0);
    });
});
