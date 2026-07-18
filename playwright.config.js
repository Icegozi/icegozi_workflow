import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    fullyParallel: true,
    reporter: 'list',
    use: {
        // Mặc định khớp APP_PORT trong .env.example; khi chạy trong container,
        // truyền PLAYWRIGHT_BASE_URL=http://127.0.0.1.
        baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8888',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],
});
