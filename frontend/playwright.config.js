import { defineConfig, devices } from '@playwright/test'
import { backendEnvironment, e2ePaths, e2eUrls, externalTarget, frontendEnvironment } from './e2e/environment.mjs'

const useSystemChrome = !process.env.CI && process.platform === 'win32'
const managedByRunner = process.env.TALENTXPANSE_E2E_MANAGED === '1'

export default defineConfig({
  testDir: './e2e',
  ...(managedByRunner || externalTarget ? {} : {
    globalSetup: './e2e/global-setup.mjs',
    globalTeardown: './e2e/global-teardown.mjs',
  }),
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  timeout: 300_000,
  expect: { timeout: 12_000 },
  reporter: process.env.CI ? [['html'], ['github']] : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: e2eUrls.app,
    actionTimeout: 12_000,
    navigationTimeout: 30_000,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'desktop',
      use: { browserName: 'chromium', channel: useSystemChrome ? 'chrome' : undefined, viewport: { width: 1440, height: 960 } },
    },
    {
      name: 'mobile',
      use: { ...devices['iPhone 13'], browserName: 'chromium', channel: useSystemChrome ? 'chrome' : undefined },
    },
  ],
  ...(managedByRunner || externalTarget ? {} : { webServer: [
    {
      command: 'php -S 127.0.0.1:8001 -t public',
      cwd: e2ePaths.backendRoot,
      env: backendEnvironment,
      url: e2eUrls.health,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: 'node node_modules/vite/bin/vite.js --host 127.0.0.1 --port 5175 --strictPort',
      cwd: e2ePaths.frontendRoot,
      env: frontendEnvironment,
      url: e2eUrls.app,
      reuseExistingServer: false,
      timeout: 120_000,
    },
  ] }),
})
