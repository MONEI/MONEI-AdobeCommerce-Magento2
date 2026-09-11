import {expect, type Locator} from '@playwright/test';

/**
 * Minimum plausible size for a rendered MONEI field. Anything under this is
 * a field the shopper cannot see.
 */
export const MIN_FIELD_HEIGHT = 30;
export const MIN_FIELD_WIDTH = 150;

/**
 * Assert a MONEI component actually rendered into its mount element.
 *
 * Geometry, not DOM presence. The regression this guards against was three
 * CardGroup parts mounted as correct iframes from js.monei.com - every DOM
 * check passed - at 0px height, so the fields were invisible. Only a bounding
 * box catches that, and it fails with a number rather than "image differs".
 *
 * Nothing inside the iframe is inspected: it is cross-origin.
 */
export async function expectRenderedField(mount: Locator, label: string): Promise<void> {
  await expect(mount, `${label}: mount element`).toBeVisible({timeout: 30_000});

  const iframe = mount.locator('iframe').first();
  await expect(iframe, `${label}: iframe present`).toHaveAttribute('src', /js\.monei\.com/, {
    timeout: 30_000
  });

  // The SDK sizes the iframe asynchronously after mount; poll rather than sleep.
  await expect
    .poll(async () => (await iframe.boundingBox())?.height ?? 0, {
      message: `${label}: iframe height`,
      timeout: 30_000
    })
    .toBeGreaterThanOrEqual(MIN_FIELD_HEIGHT);

  const box = (await iframe.boundingBox())!;
  expect(box.width, `${label}: iframe width`).toBeGreaterThanOrEqual(MIN_FIELD_WIDTH);

  const mountBox = (await mount.boundingBox())!;
  expect(mountBox.height, `${label}: mount height`).toBeGreaterThanOrEqual(MIN_FIELD_HEIGHT);
}
