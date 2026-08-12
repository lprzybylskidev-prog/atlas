import { expect, test as base } from '@playwright/test';
import type { Page, Request, Response } from '@playwright/test';

const monitoredResourceTypes = new Set(['document', 'script', 'stylesheet', 'font', 'image', 'fetch', 'xhr']);

function formatRequest(request: Request): string {
    return `${request.method()} ${request.url()}`;
}

function shouldMonitorRequest(request: Request): boolean {
    if (request.url().includes('/realtime/events') && request.failure()?.errorText === 'NS_BINDING_ABORTED') {
        return false;
    }

    return monitoredResourceTypes.has(request.resourceType());
}

function shouldMonitorResponse(response: Response): boolean {
    return shouldMonitorRequest(response.request());
}

function attachBrowserConsoleGuards(page: Page): string[] {
    const browserErrors: string[] = [];

    page.on('pageerror', (error) => {
        browserErrors.push(`pageerror: ${error.message}`);
    });

    page.on('console', (message) => {
        if (message.type() === 'error') {
            browserErrors.push(`console.error: ${message.text()}`);
        }
    });

    page.on('requestfailed', (request) => {
        if (shouldMonitorRequest(request)) {
            browserErrors.push(`requestfailed: ${formatRequest(request)} (${request.failure()?.errorText ?? 'unknown failure'})`);
        }
    });

    page.on('response', (response) => {
        if (response.status() >= 400 && shouldMonitorResponse(response)) {
            browserErrors.push(`http ${response.status()}: ${formatRequest(response.request())}`);
        }
    });

    return browserErrors;
}

const untranslatedAtlasCopyPattern =
    /(?:^|[\s([{>])(?:pages|navigation|actions|auth|breadcrumbs|datatable|filters)\.[a-z0-9_]+(?:\.[a-z0-9_]+)+(?=$|[\s,.;:!?)}\]<])/i;

export async function expectNoUntranslatedAtlasCopy(page: Page): Promise<void> {
    const visibleCopy = await page.locator('body').innerText();

    expect(visibleCopy, 'rendered UI must not expose a missing-translation marker').not.toContain('[translation:');
    expect(visibleCopy, 'rendered UI must not expose an untranslated Atlas key').not.toMatch(untranslatedAtlasCopyPattern);
}

const test = base.extend<{ page: Page }>({
    page: async ({ page }, use) => {
        const browserErrors = attachBrowserConsoleGuards(page);

        await use(page);

        if (!page.isClosed() && (await page.locator('body').count()) > 0) {
            await expectNoUntranslatedAtlasCopy(page);
        }

        expect(browserErrors, 'browser console, runtime, and monitored asset requests should stay clean').toEqual([]);
    },
});

export { expect, test };
