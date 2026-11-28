import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const playwrightDatabase = resolve('storage/framework/playwright.sqlite');

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.spec.ts',
    // Browser tests share one disposable SQLite database and fixture account.
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command:
            'php tests/Browser/prepare-database.php && cd public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
        url: 'http://127.0.0.1:8000/login',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: playwrightDatabase,
            SESSION_DRIVER: 'database',
            CACHE_STORE: 'file',
            QUEUE_CONNECTION: 'sync',
            MAIL_MAILER: 'array',
            WHATSAPP_API_VERSION: 'v99.0',
        },
    },
});
