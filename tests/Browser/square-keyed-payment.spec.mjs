/**
 * Playwright harness for Square Web Payments on staging/local.
 *
 * Proves the hosted iframe is automatable via frameLocator + sandbox cards.
 * Does not bypass Square tokenization or invent ledger rows.
 *
 * Required env:
 *   ARK_E2E_BASE_URL   e.g. https://alpha.staging.arksms.com
 *   ARK_E2E_EMAIL
 *   ARK_E2E_PASSWORD
 *   ARK_E2E_RO_PATH    e.g. /app/repair-orders/21
 *
 * Optional:
 *   ARK_E2E_CARD=success|decline  (default success → 4111…1111)
 *
 * Run (after `npx playwright install chromium`):
 *   node --experimental-vm-modules ./node_modules/@playwright/test/cli.js test tests/Browser/square-keyed-payment.spec.mjs
 *
 * Or with playwright test runner when @playwright/test is installed as a devDependency.
 */

import { test, expect } from '@playwright/test';

const baseURL = process.env.ARK_E2E_BASE_URL || '';
const email = process.env.ARK_E2E_EMAIL || '';
const password = process.env.ARK_E2E_PASSWORD || '';
const roPath = process.env.ARK_E2E_RO_PATH || '';
const mode = (process.env.ARK_E2E_CARD || 'success').toLowerCase();

const card = mode === 'decline'
    ? { number: '4000000000000002', cvv: '111', exp: '12/30', postal: '94103' }
    : { number: '4111111111111111', cvv: '111', exp: '12/30', postal: '94103' };

test.skip(!baseURL || !email || !password || !roPath, 'Set ARK_E2E_BASE_URL, ARK_E2E_EMAIL, ARK_E2E_PASSWORD, ARK_E2E_RO_PATH');

test('keyed Take payment shows processing then a visible result', async ({ page }) => {
    await page.goto(`${baseURL.replace(/\/$/, '')}/app/login`);
    await page.getByLabel(/email/i).fill(email);
    await page.getByLabel(/password/i).fill(password);
    await page.getByRole('button', { name: /log in|sign in/i }).click();
    await page.waitForURL(/\/app\//);

    await page.goto(`${baseURL.replace(/\/$/, '')}${roPath}`);
    await page.getByRole('button', { name: /take payment/i }).click();
    await page.getByRole('button', { name: /keyed card/i }).click();

    const cardRoot = page.locator('[data-payment-workspace] iframe[src*="squarecdn.com"], #ark-payment-capture-card iframe, iframe[title="Secure Credit Card Form"]').first();
    await expect(cardRoot).toBeVisible({ timeout: 20_000 });

    const frame = page.frameLocator('iframe[title="Secure Credit Card Form"], iframe[src*="single-card-element-iframe"]').first();
    await frame.locator('input[autocomplete="cc-number"], input[name="cardnumber"]').fill(card.number);
    await frame.locator('input[autocomplete="cc-exp"], input[name="exp-date"]').fill(card.exp);
    await frame.locator('input[autocomplete="cc-csc"], input[name="cvc"]').fill(card.cvv);
    const postal = frame.locator('input[autocomplete="postal-code"], input[name="postal"]');
    if (await postal.count()) {
        await postal.fill(card.postal);
    }

    const take = page.getByRole('button', { name: /take \$/i });
    await expect(take).toBeEnabled();
    await take.click();
    await expect(page.getByRole('button', { name: /processing/i })).toBeVisible({ timeout: 5_000 });

    if (mode === 'decline') {
        await expect(page.locator('[x-text="cardError"], .text-danger').filter({ hasText: /declined|cvv|postal|expiration|could not/i })).toBeVisible({ timeout: 30_000 });
    } else {
        await expect(page.getByText(/payment captured|balance|paid/i).first()).toBeVisible({ timeout: 45_000 });
    }
});
