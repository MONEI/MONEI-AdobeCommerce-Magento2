import {expect, test, type Page} from '@playwright/test';
import {
  addProductToCart,
  bustStaticCache,
  expectRealViewport,
  reachPaymentStep,
  selectMethod
} from '../helpers/checkout';
import {expectRenderedField} from '../helpers/render';

/**
 * Visual regression for every MONEI component on the checkout page.
 *
 * Each test asserts geometry first, then takes a screenshot of the method
 * block. Geometry fails with a number when a field collapses; the screenshot
 * catches colour, spacing and label regressions that geometry cannot.
 *
 * Screenshots target the method block, never the page: the surrounding theme
 * differs between platforms and is not what this suite protects.
 */

type CardLayout = 'split' | 'single';

async function activeCardLayout(page: Page): Promise<CardLayout> {
  return page.evaluate(
    () => (window as any).checkoutConfig?.payment?.monei_card?.cardInputLayout ?? 'split'
  );
}

test.beforeEach(async ({page}) => {
  await expectRealViewport(page);
  await bustStaticCache(page);
  await addProductToCart(page);
  await reachPaymentStep(page);
});

test.describe('card', () => {
  test('split layout renders three sized fields', async ({page}) => {
    const layout = await activeCardLayout(page);
    test.skip(layout !== 'split', `store is configured for the ${layout} layout`);

    const block = await selectMethod(page, 'monei_card');

    // All three parts, not just the first: the regression that motivated this
    // suite collapsed all three, and a single check would pass on any one of
    // them being fixed alone.
    await expectRenderedField(page.locator('#monei-insite-card-number'), 'card number');
    await expectRenderedField(page.locator('#monei-insite-card-expiry'), 'card expiry');
    await expectRenderedField(page.locator('#monei-insite-card-cvc'), 'card cvc');

    // Expiry and CVC share a row; they must sit beside, not below, each other.
    const expiry = (await page.locator('#monei-insite-card-expiry').boundingBox())!;
    const cvc = (await page.locator('#monei-insite-card-cvc').boundingBox())!;
    expect(Math.abs(expiry.y - cvc.y), 'expiry and cvc on one row').toBeLessThan(4);

    await expect(block).toHaveScreenshot('checkout-card-split.png');
  });

  test('single layout renders one sized field', async ({page}) => {
    const layout = await activeCardLayout(page);
    test.skip(layout !== 'single', `store is configured for the ${layout} layout`);

    const block = await selectMethod(page, 'monei_card');

    await expectRenderedField(page.locator('#monei-insite-card-input'), 'card input');

    await expect(block).toHaveScreenshot('checkout-card-single.png');
  });
});

test('bizum renders a sized button', async ({page}) => {
  const block = await selectMethod(page, 'monei_bizum');

  await expectRenderedField(page.locator('#monei_bizum_insite_container'), 'bizum');

  await expect(block).toHaveScreenshot('checkout-bizum.png');
});

test('paypal renders a sized button', async ({page}) => {
  const block = await selectMethod(page, 'monei_paypal');

  await expectRenderedField(page.locator('#monei_paypal_insite_container'), 'paypal');

  await expect(block).toHaveScreenshot('checkout-paypal.png');
});

test('express button renders above the payment methods', async ({page}) => {
  const express = page.locator('.monei-express-checkout');
  await expect(express, 'express block').toBeVisible({timeout: 30_000});

  await expectRenderedField(express.locator('.monei-express-button'), 'express button');

  // Above, not among: it is a shortcut past the form, not a choice within it.
  // The list renders asynchronously after the step opens, so wait for it - the
  // mini cart also carries an express shortcut, and measuring before the list
  // exists compares against the wrong element.
  const firstMethod = page.locator('#checkout-payment-method-load .payment-method').first();
  await expect(firstMethod).toBeVisible({timeout: 30_000});
  const expressBox = (await express.boundingBox())!;
  const methodBox = (await firstMethod.boundingBox())!;
  expect(expressBox.y, 'express sits above the first method').toBeLessThan(methodBox.y);

  await expect(express).toHaveScreenshot('checkout-express.png');
});
