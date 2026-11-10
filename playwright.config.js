import { defineConfig } from '@playwright/test';

const port = process.env.PLAYWRIGHT_PORT ?? 8123;

/**
 * Mobiele tests (docs/BOUWOPDRACHT.md §12): elk hoofdscherm op 375×667 en 430×932.
 * Draaien: npm run test:browser
 */
export default defineConfig({
    testDir: 'tests/Browser',
    outputDir: 'storage/playwright',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        locale: 'nl-NL',
        timezoneId: 'Europe/Amsterdam',
        isMobile: true,
        hasTouch: true,
        deviceScaleFactor: 2,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'iphone-se', use: { viewport: { width: 375, height: 667 } } },
        { name: 'iphone-pro-max', use: { viewport: { width: 430, height: 932 } } },
    ],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/up`,
        reuseExistingServer: !process.env.CI,
        env: { APP_ENV: 'local' },
    },
});
