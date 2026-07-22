/// <reference types="node" />

import { defineConfig, devices } from '@playwright/test';

const appUrl = process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8010';
const viteUrl = process.env.PLAYWRIGHT_VITE_URL ?? 'http://127.0.0.1:5174';

const e2eEnvironment = {
    APP_ENV: 'testing',
    APP_URL: appUrl,
    BCRYPT_ROUNDS: '4',
    CACHE_PREFIX: 'atlas_e2e_cache',
    CACHE_STORE: 'redis',
    DB_CONNECTION: 'pgsql',
    DB_DATABASE: 'atlas_e2e',
    ATLAS_RATE_LIMIT_AUTH_LOGIN_MAX_ATTEMPTS: '200',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'redis',
    REDIS_CACHE_DB: '5',
    REDIS_DB: '4',
    SESSION_DRIVER: 'redis',
    TELESCOPE_ENABLED: 'false',
    DEBUGBAR_ENABLED: 'false',
};

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
                'bash tools/testing/ensure-test-databases.sh e2e && php artisan config:clear && php artisan migrate:fresh --force && php artisan cache:clear && php artisan db:seed --class=E2eVisibilitySeeder --force && cd public && php -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            url: appUrl,
            env: e2eEnvironment,
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
