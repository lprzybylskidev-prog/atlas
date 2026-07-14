/// <reference types="node" />

import { defineConfig, devices } from '@playwright/test';

const appUrl = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8010';
const viteUrl = process.env.PLAYWRIGHT_VITE_URL ?? 'http://127.0.0.1:5174';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/support/global-setup.ts',
    globalTeardown: './tests/e2e/support/global-teardown.ts',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: appUrl,
        trace: 'on-first-retry',
        viewport: { width: 1440, height: 1000 },
    },
    webServer: [
        {
            command:
                'php artisan migrate --force && php artisan cache:clear && php artisan db:seed --class=DevelopmentDemoSeeder --force && php artisan serve --host=127.0.0.1 --port=8010',
            url: appUrl,
            env: {
                APP_URL: appUrl,
            },
            reuseExistingServer: false,
            timeout: 120_000,
        },
        {
            command: 'pnpm dev --host 127.0.0.1 --port 5174',
            url: `${viteUrl}/@vite/client`,
            reuseExistingServer: false,
            timeout: 120_000,
        },
    ],
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
    ],
});
