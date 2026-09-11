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
  return page.evaluate(() => (window as any).checkoutConfig?.payment?.monei_card?.cardInputLayout ?? 'split');
}

test.beforeEach(async ({page}) => {
  await expectRealViewport(page);
  await bustStaticCache(page);
  await addProductToCart(page);
});

/**
 * Express lives on the cart, not the checkout: a shortcut past the address
 * form belongs before the form, and the checkout's own list already offers
 * the same wallets.
 */
test('express buttons render under the cart summary', async ({page}) => {
  await page.goto('/checkout/cart/');
  const proceed = page.locator('.cart-summary button.checkout');
  await expect(proceed).toBeVisible({timeout: 60_000});

  const express = page.locator('.cart-summary .monei-express-shortcut');
  await expect(express, 'express block').toBeVisible({timeout: 30_000});

  // Both wallets, stacked, below the checkout button: the PayPal button is
  // part of express, not only a method in the checkout's list.
  await expectRenderedField(express.locator('.monei-express-wallet'), 'wallet button');
  await expectRenderedField(express.locator('.monei-express-paypal'), 'PayPal express button');
  const proceedBox = (await proceed.boundingBox())!;
  const walletBox = (await express.locator('.monei-express-wallet').boundingBox())!;
  const paypalBox = (await express.locator('.monei-express-paypal').boundingBox())!;
  expect(walletBox.y, 'wallet below the checkout button').toBeGreaterThan(proceedBox.y + proceedBox.height);
  expect(paypalBox.y, 'PayPal below the wallet').toBeGreaterThan(walletBox.y + walletBox.height - 1);

  await expect(express).toHaveScreenshot('cart-express.png');
});

test.describe('payment step', () => {
  test.beforeEach(async ({page}) => {
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
});
