import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: 'html',
    
    use: {
        trace: 'on-first-retry',
        headless: true,
        launchOptions: {
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        }
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
