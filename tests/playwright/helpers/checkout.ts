import {expect, type Locator, type Page} from '@playwright/test';

/**
 * A Spanish address on purpose: Spain is MONEI's primary market and the one
 * whose extra address rules broke express on PrestaShop.
 */
export const GUEST = {
  email: 'magento-e2e@monei.com',
  firstname: 'Test',
  lastname: 'Shopper',
  street: 'Calle Gran Via 1',
  city: 'Madrid',
  postcode: '28013',
  telephone: '600000000',
  country: 'ES',
  regionLabel: 'Madrid'
};

/**
 * A collapsed or hidden browser window reports a 0x0 viewport, and every
 * bounding box measured in it is meaningless. Every geometry assertion in
 * this suite depends on this holding.
 */
export async function expectRealViewport(page: Page): Promise<void> {
  const size = page.viewportSize();
  expect(size, 'viewport must be set').not.toBeNull();
  expect(size!.width, 'viewport width').toBeGreaterThan(300);
  expect(size!.height, 'viewport height').toBeGreaterThan(300);
}

/**
 * Force every static asset to bypass any CDN in front of the store.
 *
 * The dev store sits behind Cloudflare, which caches CSS and JS under their
 * versioned URLs for hours. Without this, a stylesheet regression keeps being
 * served from the fixed copy and the suite passes against code it never
 * loaded - which is exactly the false negative a visual suite must not have.
 * A per-run query string gives each run its own cache key; Magento ignores
 * the query on static files. Scripts are left alone, so a JS regression behind
 * a CDN still needs the store's static version bumped before the run.
 */
export async function bustStaticCache(page: Page): Promise<void> {
  const runId = process.env.MONEI_E2E_RUN_ID ?? String(Date.now());
  // Stylesheets only. RequireJS resolves script URLs itself and rewriting them
  // broke module loading, which collapsed the checkout's two-column layout.
  await page.route('**/static/**/*.css', (route) => {
    const url = new URL(route.request().url());
    url.searchParams.set('cb', runId);
    return route.continue({url: url.toString()});
  });
}

export async function addProductToCart(page: Page): Promise<void> {
  const path = process.env.MONEI_E2E_PRODUCT_PATH ?? '/joust-duffle-bag.html';
  await page.goto(path);
  await page.locator('#product-addtocart-button').click();
  // Magento confirms with a message rather than a navigation.
  await expect(page.locator('.message-success')).toBeVisible({timeout: 30_000});
}

/**
 * Fill the guest shipping step and continue to Review & Payments.
 */
export async function reachPaymentStep(page: Page): Promise<void> {
  await page.goto('/checkout/');
  await expect(page.locator('#customer-email')).toBeVisible({timeout: 60_000});

  await page.locator('#customer-email').fill(GUEST.email);
  await page.locator('[name="firstname"]').fill(GUEST.firstname);
  await page.locator('[name="lastname"]').fill(GUEST.lastname);
  await page.locator('[name="street[0]"]').fill(GUEST.street);
  await page.locator('[name="city"]').fill(GUEST.city);
  await page.locator('[name="postcode"]').fill(GUEST.postcode);
  await page.locator('[name="telephone"]').fill(GUEST.telephone);
  await page.locator('[name="country_id"]').selectOption(GUEST.country);
  await page.locator('[name="region_id"]').selectOption({label: GUEST.regionLabel});

  // Rates load after the address settles; the first one is enough.
  const rate = page.locator('.table-checkout-shipping-method input[type=radio]').first();
  await expect(rate).toBeVisible({timeout: 30_000});
  await rate.check();

  await page.locator('button.continue').click();
  await expect(page.locator('.checkout-payment-method')).toBeVisible({timeout: 60_000});
}

/**
 * Pick a MONEI payment method by its radio id and wait for its block to open.
 */
export async function selectMethod(page: Page, code: string) {
  const radio = page.locator(`#${code}`);
  await expect(radio).toBeVisible({timeout: 30_000});
  await radio.check();
  const block = page.locator(`.payment-method#${code}, .payment-method:has(#${code})`).first();
  await expect(block).toHaveClass(/_active/, {timeout: 20_000});
  await settleBillingAddress(block);

  return block;
}

/**
 * Magento's billing-address component starts with "same as shipping" off and
 * only turns it on from a quote.billingAddress subscription. When the address
 * resolves before the component subscribes, the box stays off and the block
 * grows an Edit button. That race is Magento's, so the helper forces the
 * settled state rather than letting it decide the screenshot's height.
 */
async function settleBillingAddress(block: Locator): Promise<void> {
  const sameAsShipping = block.locator('input[name="billing-address-same-as-shipping"]');
  if ((await sameAsShipping.count()) === 0) {
    return;
  }
  if (!(await sameAsShipping.isChecked())) {
    await sameAsShipping.check();
  }
  await expect(sameAsShipping).toBeChecked();
  await expect(block.locator('.action-edit-address')).toBeHidden();
  // Ticking the box re-saves the billing address behind a spinner.
  await expect(block.locator('.loading-mask:visible, .loader:visible')).toHaveCount(0, {timeout: 20_000});
}
