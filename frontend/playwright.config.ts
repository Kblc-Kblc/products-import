import { defineConfig, devices } from '@playwright/test';

/** e2e tests run against the full docker stack: `make up`, then `npm run e2e`. */
export default defineConfig({
  testDir: './e2e',
  timeout: 60_000,
  retries: process.env['CI'] ? 1 : 0,
  reporter: process.env['CI'] ? 'github' : 'list',
  use: {
    baseURL: process.env['BASE_URL'] ?? 'http://localhost:8080',
    locale: 'ru-RU',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
