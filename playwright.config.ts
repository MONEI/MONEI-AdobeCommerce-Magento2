import {defineConfig, devices} from '@playwright/test';
import {config} from 'dotenv';

config({path: ['tests/playwright/.env', 'tests/playwright/.env.local'], quiet: true});

// The store is external - a running Magento behind an HTTPS tunnel - so there
// is no webServer block. See tests/playwright/.env.example.
const baseURL = process.env.MONEI_E2E_BASE_URL ?? 'https://magento.monei-dev-tunnel.com';

export default defineConfig({
  testDir: './tests/playwright/specs',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  // No retries - flaky tests are bugs. Tests must be deterministic.
  retries: 0,
  workers: 1,
  reporter: process.env.CI ? [['github'], ['html', {open: 'never'}]] : 'html',
  timeout: 90_000,
  // Single source of truth for screenshot baselines: macOS (darwin). CI runs on
  // Linux but compares against darwin baselines - the threshold and
  // maxDiffPixelRatio below absorb cross-platform font anti-aliasing drift.
  // Same convention as monei-js.
  snapshotPathTemplate: '{testDir}/{testFilePath}-snapshots/{arg}-{projectName}-darwin{ext}',
  expect: {
    timeout: 20_000,
    toHaveScreenshot: {
      threshold: 0.3,
      // Tight enough that a missing field or wrong colour still fails; loose
      // enough to absorb mac/linux anti-aliasing. Per-test overrides must carry
      // a comment justifying the bump.
      maxDiffPixelRatio: 0.1,
      animations: 'disabled'
    }
  },
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off'
  },
  projects: [
    {name: 'chromium', use: {...devices['Desktop Chrome']}},
    {name: 'mobile', use: {...devices['Pixel 5']}}
  ]
});
