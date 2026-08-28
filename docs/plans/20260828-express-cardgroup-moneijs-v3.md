# Express Checkout, CardGroup and monei.js v3 for Magento

## Overview

Port three features from the PrestaShop and WooCommerce rounds into `Monei_MoneiPayment`:

1. **monei.js v2 → v3** — a breaking SDK bump that currently breaks two live payment methods.
2. **CardGroup split card fields** — separate number / expiry / CVC inputs, as the **new default**.
3. **Express checkout** — wallet buttons on cart, mini-cart, product page and checkout.

Source handoff: `/tmp/handoff-magento-express-cardgroup.md`. Prior art: PrestaShop
[PR #100](https://github.com/MONEI/MONEI-PrestaShop/pull/100), WooCommerce
[#116](https://github.com/MONEI/MONEI-WooCommerce/pull/116),
[#117](https://github.com/MONEI/MONEI-WooCommerce/pull/117),
[#118](https://github.com/MONEI/MONEI-WooCommerce/pull/118).

Revised after plan review. All file:line claims below were verified against the repo.

## Context (from discovery)

| Fact | Evidence |
|---|---|
| monei.js **v2** | `view/frontend/requirejs-config.js:4` |
| `moneijs` shimmed with `exports: 'monei'` — 9 files depend on it | `view/frontend/requirejs-config.js:9-11` |
| `monei.CardInput` missing `amount`+`currency` | `monei-card-method.js:153` |
| `monei.Bizum` missing `amount`+`currency` | `monei-bizum-method.js:91` |
| `monei.PayPal` already passes both | `monei-paypal-method.js:109,113` |
| `monei.PaymentRequest` already passes both | `monei-google-apple-method.js:158-160` |
| `createToken(cardInput)` | `monei-card-method.js:202` |
| **`monei.api.getPayment()`** — v2 surface, status poller | `monei-payment-loading.js:38-39` |
| Client-computed amount | `monei-google-apple-method.js:160`, `monei-paypal-method.js:113` |
| CSP has only `js.monei.com` + `api.monei.com` | `etc/frontend/csp_whitelist.xml` |
| **73 test classes / 418 test methods**, none run in CI | `Test/`, `.github/workflows/main.yml` is release-zip only |
| **No `phpunit.xml` in this repo** | `composer test` → `./bin test` → Magento's `dev/tests/unit/phpunit.xml.dist` |
| **No JS test tooling** | `package.json` has commitlint/prettier/husky/lint-staged/release-it only |
| i18n CSV, 14 locales, generator exists | `i18n/*.csv`, `bin i18n:collect-phrases` |
| `json_style` is CardInput-shaped, merchant-editable | `etc/config.xml:56` |
| SDK `monei/monei-php-sdk` ^2.8.3, module v2.2.4 | `composer.json` |

### What this module already has — reuse, do not rebuild

| Existing | Use for |
|---|---|
| `etc/webapi.xml` — **exists**, 4 live routes, `anonymous`/`self` pattern | extend, never recreate |
| `Api/Service/Checkout/`, `Api/Service/` interfaces | express service interface |
| Payment methods as `virtualType` of `Magento\Payment\Model\Method\Adapter` in `etc/di.xml:140,202,265,297,348,393,425` | express method — **no `Model/Method/` class**, that directory does not exist |
| `Model/Payment/Monei.php:62` `PAYMENT_METHODS_MONEI` | must list the express code |
| `Service/Quote/GetAddressDetailsByQuoteAddress.php`, `GetCustomerDetailsByQuote.php` | express address mapping |
| `Service/Shared/CountryPaymentMethods.php`, `ApplePayAvailability.php`, `GooglePayAvailability.php`, `AvailablePaymentMethods.php` | express availability |
| `Service/Checkout/AbstractCheckoutService.php` | express service base |
| `Model/CheckoutConfigProvider.php` | server-side amount source |

### The MONEI payment lifecycle — and why express must be sequenced around it

This module is **two-phase**, unlike Stripe:

```
$quote->reserveOrderId()                          CreateGuestMoneiPaymentInSite.php:132
  → CreatePaymentInterface::execute()                                          :147
  → paymentId
  → client monei.confirmPayment({paymentId, paymentToken})   payment-handler.js:43
  → nextAction.redirectUrl                                                     :45
```

Verified against `https://js.monei.com/api/v1/openapi.json`:

| Endpoint | Amount |
|---|---|
| `POST /payments` | **required** (`amount`, `currency`, `orderId`) |
| `POST /payments/{id}/confirm` | **not accepted** — takes `billingDetails`, `shippingDetails`, `paymentToken`, `customer`, `metadata`, `paymentMethod`, `sessionDetails`, `generatePaymentToken` |
| update/patch a payment | **does not exist** |

`Service/Api/` confirms this: Create, Confirm, Capture, Cancel, Refund, Get — **no Update**.

**Consequence, and the central design constraint of this plan:** the charged amount is
immutable from the moment the payment is created. Therefore **the MONEI payment must be
created *after* the final total is known**, server side, inside the place-order call —
never before the wallet sheet opens. `confirm` accepting `shippingDetails` and
`billingDetails` is the seam that makes this work.

### The wallet sheet updates its own total — verified

An earlier draft, following the handoff, claimed MONEI's `PaymentRequest` has no
shipping-change callback. **That is wrong** — but the first attempt to disprove it cited the
wrong evidence. The `onShipping*` implementations in the v3 bundle (bytes 71278-72001) belong
to the **PayPal** component; they patch PayPal's Orders API. `PaymentRequest` (`Kn`, tag
`monei-payment-request`) is a zoid component that forwards props blind into an iframe, so the
bundle proves nothing either way about it.

**Proven in the SDK implementation**, not just the types:
`monei-js/apps/payment-request/src/apple-pay.ts` wires `session.onshippingcontactselected` →
`props.onShippingAddressChange(address)` → `session.completeShippingContactSelection({newTotal:
…})` (lines 127-160), and `session.onshippingmethodselected` → `props.onShippingOptionChange`
→ `completeShippingMethodSelection` (lines 179-215). Amounts are cents, divided by 100 for the
sheet. The published type contract agrees — `@monei-js/components` `dist/index.d.ts`,
`interface PaymentRequestProps` (line 636):

```ts
requestShipping?: boolean;
requestBilling?: boolean;
shippingOptions?: ShippingOption[];
onShippingAddressChange?: (address: Address) =>
    Promise<ShippingAddressChangeResult> | ShippingAddressChangeResult;
onShippingOptionChange?: (option: ShippingOption) =>
    Promise<ShippingOptionChangeResult> | ShippingOptionChangeResult;
```

`ShippingAddressChangeResult { shippingOptions?, amount? }` and
`ShippingOptionChangeResult { amount? }` both return an updated amount, so the sheet total is
live. `ShippingOption { id, label, description?, amount, type?, selected? }` is the shape
Task 7 must map Magento carriers into.

`SubmitResult` is what the wallet returns on approval:
`{ token?, error?, paymentMethod?, finalAmount?, billingDetails?, shippingDetails?,
shippingOption? }` — it carries the chosen `shippingOption` and both addresses. Treat
`finalAmount` as a cross-check only, never as the charged amount.

**Hard constraint from the bundle:** `requestShipping` throws
`"requestShipping requires accountId flow. Use accountId instead of paymentId."` Express
must therefore use the `accountId` flow and create the payment server side afterwards —
which is exactly the ordering Task 8 already requires.

### Reference implementation: Stripe

Read from `stripe/stripe-magento2-releases` v4.6.5. Stripe **does** have controllers
(`Controller/{Adminhtml,Customer,Payment,Subscriptions,Webhooks}`); what is true is that
its *express* layer uses webapi routes instead. Shapes worth borrowing:

- Express served by `etc/webapi.xml` routes with `<resource ref="anonymous"/>`.
- Order placement via `$quoteManagement->submit($quote)` after `$quote->collectTotals()` —
  Magento's own quote→order path, not a second one (`Api/Service.php:383`).
- Dispatches `checkout_type_onepage_save_order_after` **and** `checkout_submit_all_after`
  (`:404`); sets `setLastQuoteId`, `setLastSuccessQuoteId`, `setLastOrderId`,
  `setLastRealOrderId`, `setLastOrderStatus`.
- Client never sends an amount. Server recomputes.
- Mounting via the `shortcut_buttons_container` event — one observer serves cart, mini-cart
  and product page, split by `getIsCatalogProduct()` / `getIsShoppingCart()`.
- Product-page express needs extra quote endpoints: `addtocart` (`:450`),
  `restore_quote` (`:704`), `update_cart` (`:761`).
- Placeholder billing values for wallets that hide personal data, corrected later by webhook.

**Directly applicable.** Stripe wires `shippingaddresschange` / `shippingratechange`
(`stripe_payments_express.js:663-664,456,549`) to server calls that return fresh
`lineItems` + `shippingRates`, then `event.resolve(payload)` updates the sheet.
**MONEI has the same capability** — see below. Copy this shape.

## Development Approach

- **Testing approach**: **Regular** (code first, tests after) — user decision.
- Tests remain a required deliverable of every task, written before the task closes.
- **All tests pass before the next task starts.** No exceptions, no "pre-existing".
- Update this plan when scope changes.
- Backward compatible for merchants on v2.2.4, except the deliberate CardGroup default flip.

## Testing Strategy

- **Unit (PHPUnit)** — 418 test methods across 73 classes must stay green throughout.
- **E2E (Playwright)** — new suite at `tests/playwright/`, matching the sibling plugins.
- **No JS unit tests.** The repo has no jest/karma/jasmine. Standing that up is not in
  scope; knockout and component behaviour is covered by Playwright instead.
- **Baseline first.** Task 1 covers the existing card, Bizum, PayPal, wallet **and the
  post-redirect status poller** before Task 2 touches the SDK.
- Never assert on Bizum's `E650` — Redsys `BIZ00202`, not our defect. Cover wiring only.
- Payment test failing? Check the payment status in the DB before blaming the code.
- Do not run overlapping e2e suites against one store.

## Progress Tracking

`[x]` when done · ➕ new task · ⚠️ blocker

## Solution Overview

| Decision | Choice | Why |
|---|---|---|
| Express order creation | `CreatePayment` → `quoteManagement->submit()` → `ConfirmPayment` | Amount is immutable at create, so create once the total is final — but `CreatePayment` needs only a *reserved* order id, so it precedes `submit()` |
| Sheet total | live-updated via `onShippingAddressChange` / `onShippingOptionChange` | Shopper approves the amount they are actually charged |
| Transport | extend existing `etc/webapi.xml` | Already the module's pattern |
| Express method | `di.xml` virtual type | Matches all seven existing methods |
| Mounting | `shortcut_buttons_container` observer | Magento's own extension point; covers 3 of 4 surfaces |
| Amount authority | Server, always | Handoff rule; closes a live tampering surface |
| CardGroup | New default | User decision |
| E2E | Playwright | User decision |

## Prerequisites — dev environment

**Outside this repo** (`/Users/dmitriy/Work/magento2`), so nothing here is committable and
the per-task test discipline does not apply. Do it before Task 1.

- Start the stack; confirm Magento 2.4.8-p1 boots with the module loaded.
- Bring up the cloudflared tunnel to `magento.monei-dev-tunnel.com` (certs already exist).
- Set `web/secure/base_url`, `use_in_frontend`, `use_in_adminhtml`, and
  `web/secure/offloader_header` = `X-Forwarded-Proto`. Verify no `http://` links and no
  dropped session cookie mid-checkout.
- Raise php-fpm `pm.max_children`; confirm no 502 on order confirm.
- Disable transactional email so order confirmation cannot block.
- Disable `dev/js/merge_files`, `dev/js/minify_files`, `dev/css/merge_css_files`; confirm
  edited JS reaches the browser without a static-content deploy.
- Verify: one card payment end to end through the tunnel, order reaches `processing`.

## Implementation Steps

### Task 1: Playwright harness and baseline regression net

Green before Task 2 touches the SDK. This is the net that catches the v3 breakage.

**Files:**
- Create: `tests/playwright/playwright.config.ts`, `.env.example`, `helpers/checkout.ts`
- Create: `tests/playwright/specs/{card,bizum,paypal,wallet,status-poller}.spec.ts`
- Modify: `package.json` (Playwright devDependency, `test:e2e` script)
- Modify: `.gitignore` (`tests/playwright/.env`, `test-results/`, `playwright-report/`)

- [ ] scaffold Playwright against the tunnel URL; port helpers from the PrestaShop suite
- [ ] `.env.example` documents required vars; real keys stay gitignored and uncommitted
- [ ] baseline: card payment succeeds (current v2 `CardInput`)
- [ ] baseline: Bizum renders and submits (assert wiring, **not** `E650`)
- [ ] baseline: PayPal renders and redirects
- [ ] baseline: wallet button renders when supported
- [ ] baseline: **post-redirect status poller** completes — covers `monei.api.getPayment()`
- [ ] run the suite three consecutive times, green all three, no overlapping runs

### Task 2: monei.js v3 bump

Atomic. Splitting this ships a broken checkout.

**Files:**
- Modify: `view/frontend/requirejs-config.js`
- Modify: `monei-card-method.js`, `monei-bizum-method.js`, `monei-payment-loading.js`
- Modify: affected `Test/Unit/` config tests

- [ ] **first**: confirm the v3 bundle's UMD global still exports `monei`, or fix the shim at
      `requirejs-config.js:9-11` — nine `define()` callers get `undefined` if it changed
- [ ] **first**: confirm whether `createToken(CardInput)` still works in v3. If it does,
      defer the `submit()` switch to Task 3 and keep this task minimal
- [ ] point `moneijs` at `https://js.monei.com/v3/monei.js` (`requirejs-config.js:4`)
- [ ] add `amount` + `currency` to `monei.CardInput` (`monei-card-method.js:153`)
- [ ] add `amount` + `currency` to `monei.Bizum` (`monei-bizum-method.js:91`)
- [ ] verify `monei.api.getPayment()` still exists in v3 (`monei-payment-loading.js:38`);
      migrate the status poller if the API surface moved
- [ ] switch `createToken` → `submit()` at `monei-card-method.js:202` **only if** the check
      above showed it is required
- [ ] update unit tests covering changed renderer config
- [ ] run Task 1's full baseline suite — all six specs must pass
- [ ] run PHPUnit — 418 methods green before Task 3

### Task 3: CardGroup split card fields as the default

**Files:**
- Create: `view/frontend/web/js/view/payment/method-renderer/monei-card-group.js`
- Create: `view/frontend/web/template/payment/monei-card-group.html`
- Modify: `monei-card-method.js`, `etc/config.xml`, `etc/adminhtml/system.xml`
- Create: `Test/Unit/.../CardGroupConfigTest.php`

- [ ] `monei.CardGroup` with `accountId`, `amount`, `currency`, `style`, `onChange`, `onEnter`
- [ ] render `CardNumber`, `CardExpiry`, `CardCvc` as `({group}).render(el)` — pass **no**
      amount or currency to the parts, they throw
- [ ] tokenise with `group.submit()`
- [ ] destroy parts **before** the group on teardown
- [ ] admin setting for card layout, defaulting to split
- [ ] **migrate or validate `json_style`** — `etc/config.xml:56` is CardInput-shaped and
      merchants have edited it; applying it unchanged to CardGroup gives every merchant
      broken-looking fields on upgrade. Confirm whether the second default at
      `etc/config.xml:79` (`{"height":"45px"}`) is also in scope
- [ ] template and styles; no `em` heights
- [ ] unit tests for config/default resolution and the style migration
- [ ] **update `card.spec.ts`** for the new default; move single-field assertions behind the toggle
- [ ] e2e: split-field payment succeeds; single-field still works when configured
- [ ] run full suite before Task 4

### Task 4: Express checkout configuration

**Files:**
- Create: `Model/ExpressCheckout/Config.php`
- Modify: `etc/config.xml`, `etc/adminhtml/system.xml`
- Create: `Test/Unit/Model/ExpressCheckout/ConfigTest.php`

- [ ] global enable flag plus per-surface flags (cart, mini-cart, product, checkout)
- [ ] reuse `Service/Shared/CountryPaymentMethods.php` for country gating — do not duplicate
- [ ] reuse `ApplePayAvailability` / `GooglePayAvailability` for wallet gating
- [ ] one `style` object following the existing `json_style` pattern — **no theme matrix**,
      MONEI's `PaymentRequest` takes a single style
- [ ] unit tests for every flag, including each disabled path
- [ ] run tests before Task 5

### Task 5: Express service scaffolding and params endpoint

**Spike resolved — server-side confirm works.** Read from MONEI source (`~/Work/MONEI`,
repos on feature branches but clean, so possibly ahead of production):

1. `SubmitResult.token` is **already a MONEI token, not a wallet network token**.
   `monei-js/apps/payment-request/src/apple-pay.ts` `onpaymentauthorized` exchanges the raw
   Apple Pay token via `api.createToken({accountId, paymentId, sessionId, paymentMethod:
   {applePay: {token}}})` and returns `result.token`.
2. In the express (**accountId**) flow that call passes **no `amount`/`currency`**
   (`monei-js/packages/components/src/api.ts:51-72`), so the token is not amount-bound and can
   be confirmed against a payment created afterwards at the final total. This is what makes
   Task 8's ordering safe.
3. The server API path `payments-service/src/confirmPayment/public.ts` routes to the same
   `confirmPayment()` core as the browser path, with no token-type restriction that excludes
   wallet tokens — the only `TokenType` branch (`confirmPayment.ts:141`) decrypts encrypted
   tokens.
4. The module already confirms MONEI tokens server side at
   `Service/Checkout/CreateLoggedMoneiPaymentVault.php:253`.

**Two constraints this imposes:**

- **Pass `sessionDetails` to `ConfirmPayment`.** `public.ts` forwards `event.sessionDetails`,
  and `confirmPayment.ts` skips fraud screening for sessionless operations. A server-side
  confirm with no session details is screened as merchant-initiated. Forward what the browser
  can supply.
- **The wallet sheet closes before the charge is confirmed.** `apple-pay.ts` calls
  `session.completePayment()` on the **tokenization** result, not the payment result. So a
  decline surfaces *after* the sheet is gone. Task 11's failure surfacing is therefore
  mandatory, not defensive.

**Files:**
- Create: `Api/Service/Checkout/ExpressCheckoutInterface.php`
- Create: `Service/Checkout/ExpressCheckout.php`
- **Modify** (never create): `etc/webapi.xml`
- Modify: `etc/di.xml`
- Create: `Test/Unit/Service/Checkout/ExpressCheckoutTest.php`

Paths follow the module's own convention — interfaces in `Api/Service/Checkout/`,
implementations in `Service/Checkout/`. `Model/Api/` holds only `MoneiApiClient.php`.

- [ ] `getParams($location, $productId)` — **accountId** (never paymentId; `requestShipping`
      throws on the paymentId flow), currency, `requestShipping`, `requestBilling`,
      per-surface config, opening amount, initial `shippingOptions` when the address is known
- [ ] add routes to the existing `webapi.xml` alongside the four live ones, same
      `<resource ref="anonymous"/>` pattern
- [ ] extend `Service/Checkout/AbstractCheckoutService.php`
- [ ] unit tests: each surface, disabled config, virtual vs physical cart
- [ ] run tests before Task 6

### Task 6: Wallet address mapping

New code. The module has **no** wallet→Magento address mapper. `Service/Quote/GetAddressDetailsByQuoteAddress.php:25-56`
and `GetCustomerDetailsByQuote.php:25-44` map the **opposite** direction (Magento → MONEI) and
cannot be reused here. Stripe needed two purpose-built helpers for this.

**Files:**
- Create: `Service/Quote/SetExpressAddressesOnQuote.php`
- Modify: `Service/Checkout/ExpressCheckout.php`
- Create: `Test/Unit/Service/Quote/SetExpressAddressesOnQuoteTest.php`

- [ ] map a wallet address payload to a Magento address — full variant, for post-approval
- [ ] map a **partial** variant, for the pre-approval sheet callbacks where wallets expose
      only country / postcode / city
- [ ] set billing and shipping on the quote; apply the partial address to **billing** too so
      virtual-cart tax is correct
- [ ] region-required countries: reject with a clear message when region is missing
- [ ] placeholder values for wallets that hide personal data — `submit()` validates
      `firstname`, `lastname`, `street`, `city`, `telephone` on both addresses
      (`Magento/Customer/Model/Address/Validator/General.php:67-99`), so placeholders are
      mandatory, not optional
- [ ] on the checkout surface, do not clobber an email already on the quote
- [ ] unit tests: full address, partial address, missing region, missing telephone,
      PayPal-balance partial address, virtual cart, checkout-surface email preservation
- [ ] run tests before Task 7

### Task 7: Shipping options for the wallet sheet

Feeds `onShippingAddressChange` / `onShippingOptionChange` so the sheet shows the real total
before approval. Mirrors Stripe's `ece_shipping_address_changed` / `ece_shipping_rate_changed`;
read `stripe/.../Api/Response/ECEResponse.php` before writing this.

**Files:**
- Modify: `Api/Service/Checkout/ExpressCheckoutInterface.php`, `Service/Checkout/ExpressCheckout.php`,
  `etc/webapi.xml`
- Modify: `Test/Unit/Service/Checkout/ExpressCheckoutTest.php`

- [ ] `getShippingOptions($address, $location)` — apply the partial address, collect rates,
      return `ShippingOption[]` **and** the recalculated amount
- [ ] `selectShippingOption($address, $optionId)` — apply the carrier, return the new amount
- [ ] use `ShipmentEstimationInterface::estimateByExtendedAddress()`, **not**
      `ShippingInformationManagement::saveAddressInformation()` — the latter validates
      firstname/street/telephone that wallets do not provide until approval, and overwrites
      the partial address with the customer's default
- [ ] clear personal address fields before estimating — otherwise a logged-in customer's
      saved firstname/street/telephone persist alongside the wallet's partial location data
      and poison the rate calculation
- [ ] map Magento carrier/method codes into `ShippingOption {id, label, description?, amount,
      type?, selected?}`; the `id` must round-trip to `setShippingMethod`
- [ ] amounts in **base currency cents**, matching `AbstractCheckoutService.php:137`
      (`(int)($quote->getBaseGrandTotal()*100)`), or multi-currency stores disagree
- [ ] restore the previously selected shipping method when it survives the address change
- [ ] cap the returned rates — Stripe caps at 9; wallets do not render unbounded lists
- [ ] return a clear failure when no carrier serves the address, so the wallet rejects it
      rather than showing a total the server will not honour
- [ ] unit tests: multiple carriers, single carrier, no carrier, virtual cart, option id
      round-trip, base-currency conversion, logged-in customer with a saved address
- [ ] run tests before Task 8

### Task 8: Express order submission and MONEI payment

**Payment first, order second.** The amount is immutable at create, so the payment is created
after totals are final — but `CreatePayment` needs only a *reserved* order id, not an order
(`Service/Checkout/AbstractCheckoutService.php:151,161,172,191` builds
`'order_id' => $quote->getReservedOrderId()`). Creating the order first would strand it on any
create failure: `pending_payment`, `monei_payment_id` NULL, invisible to
`Cron/ProcessPendingOrders.php:127-128`, holding decremented inventory.

**Files:**
- Modify: `Service/Checkout/ExpressCheckout.php`
- Modify: `Test/Unit/Service/Checkout/ExpressCheckoutTest.php`

- [ ] `placeOrder($result, $location)` takes the `SubmitResult` payload — token,
      `billingDetails`, `shippingDetails`, `shippingOption` — **and rejects any
      client-supplied amount**; `finalAmount` is a cross-check only
- [ ] guard: empty cart / expired session throws a clear error
- [ ] apply the sheet's chosen carrier:
      `setCollectShippingRates(true)->collectShippingRates()->setShippingMethod($id)`, and
      throw when no method is set
- [ ] **`setTotalsCollectedFlag(false)` before `collectTotals()`** — bare `collectTotals()`
      early-returns on an already-collected quote (`Magento/Quote/Model/Quote.php:2036-2038`),
      so the payment would be created at the **pre-shipping** total. Silent, and no
      mocked-quote unit test catches it
- [ ] `$quote->reserveOrderId()`
- [ ] reuse `AbstractCheckoutService::checkExistingPayment()` (`:128`) — **but note both
      existing callers early-return on a hit** (`CreateGuestMoneiPaymentInSite.php:136-139`,
      `CreateLoggedMoneiPaymentVault.php:138-141`); express must fall through to submit
- [ ] **`CreatePayment`** — required args are `amount`, `currency`, `order_id` **and
      `shipping_details`** (`Service/Api/CreatePayment.php:45-49`)
- [ ] set `allowed_payment_methods` from `Service/Shared/PaymentMethodMap.php:27` so the
      payment is restricted to Google Pay / Apple Pay. Note no in-site service does this today
      — `Controller/Payment/Redirect.php:93-95` is the only consumer
- [ ] `savePaymentIdToQuote()`
- [ ] **normalise the guest branch manually.** `setCheckoutMethod` is only read by
      `QuoteManagement::placeOrder()` (`:444`), which `submit()` bypasses. Replicate
      `setCustomerId(null)`, `setCustomerEmail`, `setCustomerIsGuest(true)`,
      `setCustomerGroupId(NOT_LOGGED_IN_ID)`, `setCustomerFirstname/Lastname`, `setRemoteIp`,
      `setXForwardedFor` — otherwise guest orders get the wrong customer group, hence wrong
      tax and pricing rules, and no IP for fraud review
- [ ] **`$quote->getPayment()->importData(['method' => EXPRESS_CODE, 'additional_data' => …])`
      before submit** — `submit()` runs `PaymentMethodValidationRule`
      (`Magento/Quote/etc/di.xml:120`); without this, express place-order fails 100% of the time
- [ ] **write the payment id onto the order immediately after submit** — payment
      `additional_information` **and** the `monei_payment_id` column. `etc/fieldset.xml` does
      not copy it, and `Model/PaymentProcessor.php:839` only runs on a webhook that an
      unconfirmed payment never triggers
- [ ] `$quoteManagement->submit($quote)` → order
- [ ] dispatch `checkout_type_onepage_save_order_after` and `checkout_submit_all_after`.
      `checkout_submit_before` fires from `placeOrder()` (`:473`), not `submit()` — dispatch it
      too if third-party compatibility matters
- [ ] **`ConfirmPayment`** with `paymentToken`, `billingDetails`, `shippingDetails` **and
      `sessionDetails`** — omit the last and the payment is fraud-screened as merchant-initiated
- [ ] **write the confirmed addresses back onto the order** — `PaymentProcessor.php:801-841`
      only writes `additional_information` and the payment id; it never touches order
      addresses, so Task 6's placeholders would otherwise be permanent and the order
      undeliverable
- [ ] **rollback on confirm failure**: `submit()` deactivates the quote
      (`QuoteManagement.php:514,636`), so the shopper's cart is gone. Restore it, mirroring
      Stripe's `restore_quote` (`Api/Service.php:704-731`)
- [ ] set `setLastQuoteId`, `setLastSuccessQuoteId`, `setLastOrderId`, `setLastRealOrderId`,
      `setLastOrderStatus` **only after confirm returns** — earlier, and a shopper reaches a
      success page for an unpaid order
- [ ] return `nextAction.redirectUrl`
- [ ] unit tests: happy path, empty cart, no shipping method, create failure, confirm failure,
      guest and customer branches, client-amount rejection
- [ ] **integration-level test asserting the created payment amount equals
      `$order->getGrandTotal()`** — the mocked-quote unit tests cannot catch the
      `collectTotals` or `importData` defects
- [ ] unit tests asserting post-failure state: create failure leaves no order and no
      decremented inventory; confirm failure leaves a reconcilable order and a restored cart
- [ ] run tests before Task 9

### Task 9: Register the express payment method

**Files:**
- Modify: `etc/di.xml`, `etc/config.xml`, `etc/payment.xml`, `Model/Payment/Monei.php`
- Modify: `Test/Unit/Model/Payment/MoneiTest.php`

- [ ] `MoneiExpressPaymentFacade` + config + ValueHandlerPool as **virtual types**, matching
      `etc/di.xml:140,202,265,297,348,393,425` — no `Model/Method/` class
- [ ] reuse `MoneiCommandPool`; do not fork the gateway commands
- [ ] add `EXPRESS_CODE` to **both** `PAYMENT_METHODS_MONEI` (`Model/Payment/Monei.php:62`)
      and `PAYMENT_METHOD_MAP` (`:77`)
- [ ] wire `Service/Shared/PaymentMethodMap.php` for `allowed_payment_methods`
- [ ] verify the five dependents handle the new code:
      `Observer/SaveOrderBeforeSalesModelQuoteObserver.php` (fires on
      `sales_model_service_quote_submit_before`, which `submit()` **does** dispatch),
      `Plugin/OrderCancel.php`, `Plugin/OrderStatusAfterRefund.php`,
      `Plugin/OrderInvoiceEmailSent.php`, `Plugin/CheckoutShippingInformationManagement.php`
- [ ] audit the seven files that branch on method codes without those constants:
      `Helper/PaymentMethod.php`, `Helper/PaymentMethodFormatter.php`,
      `Model/CheckoutConfigProvider.php`, `Model/Data/PaymentDTO.php`,
      `Block/Monei/Customer/CardRenderer.php`, `Service/Order/CreateVaultPayment.php`,
      `Service/Shared/PaymentMethodCodeMapper.php`
- [ ] hide `monei_express` from the normal checkout payment list — it is a shortcut method
- [ ] unit tests for availability, both code arrays, and each dependent
- [ ] e2e: express order does not email before payment confirms
- [ ] run full suite before Task 10

### Task 10: Product-page cart endpoints

**Files:**
- Modify: `Api/Service/Checkout/ExpressCheckoutInterface.php`, `Service/Checkout/ExpressCheckout.php`,
  `etc/webapi.xml`
- Modify: `Test/Unit/Service/Checkout/ExpressCheckoutTest.php`

- [ ] `addToCart($params)` — add the product to the shopper's **existing** cart, removing any
      duplicate line for the same product. Note the consequence, which Stripe accepts:
      express-from-PDP charges cart + product, and on abandon the product stays in the cart
- [ ] `restoreQuote()` — this is the **rollback** Task 8 needs for a failed placement, not an
      abandon-restore. Stripe implements no abandon-restore. Share one implementation
- [ ] Stripe's third endpoint `update_cart` is **deliberately out of scope for v1** — quantity
      changes from inside the wallet sheet are not supported
- [ ] unit tests: simple product, configurable product, duplicate line removal, rollback
- [ ] run tests before Task 11

### Task 11: Mount express on cart, mini-cart and product page

**Files:**
- Create: `Observer/AddExpressButton.php`, `Block/Express/Shortcut.php`
- Create: `view/frontend/templates/express/shortcut.phtml`
- Create: `view/frontend/web/js/view/express/monei-express.js`
- Modify: `etc/frontend/events.xml`, `view/frontend/web/js/utils/error-handler.js`
- Create: `Test/Unit/Observer/AddExpressButtonTest.php`

- [ ] observe `shortcut_buttons_container` in **`etc/frontend/events.xml`** — it is a
      frontend-only event and that directory already exists
- [ ] split surfaces: product = `is_catalog_product`, cart = `is_shopping_cart`,
      mini-cart = neither. Honour the per-surface flags
- [ ] **mini-cart needs its own init/teardown lifecycle** — its markup comes from
      `Magento\Checkout\CustomerData\Cart:101`, delivered as customer-data JSON and cached in
      localStorage, not rendered with the page. An iframe mounted there does not behave like
      one on the cart page
- [ ] render `monei.PaymentRequest` with `requestShipping`, using the **accountId** flow
- [ ] wire `onShippingAddressChange` → `getShippingOptions`, `onShippingOptionChange` →
      `selectShippingOption`, returning updated amounts so the sheet total is live
- [ ] reject the address in the sheet when no carrier serves it
- [ ] on submit, pass the `SubmitResult` through; **never send an amount**
- [ ] failure surfacing: errors render on the surface that started the payment, keyed to the
      originating button — never to Magento's active payment method. Never discard a rejected
      express order silently; restore the cart and explain
- [ ] no `display:flex` on the wallet container; no `em` button heights
- [ ] verify placement empirically against the **active** theme
- [ ] unit tests: observer per surface, every disabled path, error ownership
- [ ] e2e: express succeeds from cart, mini-cart, product page
- [ ] e2e: sheet total updates on option change, and the charged amount equals the total the
      sheet last showed
- [ ] e2e: declined express surfaces the error on the originating surface, cart intact
- [ ] run full suite before Task 12

### Task 12: Mount express on the checkout page

**Files:**
- Create: `view/frontend/web/js/view/express/monei-express-checkout.js`
- Create: `view/frontend/web/template/express/checkout.html`
- Modify: `view/frontend/layout/checkout_index_index.xml`
- Create: `Test/Unit/Block/Express/CheckoutConfigTest.php`

- [ ] mount above the payment step in the checkout UI component
- [ ] reuse the express service; the quote's existing billing email must survive
- [ ] same failure-ownership rules as Task 11
- [ ] unit tests for the checkout-surface config resolution and email preservation
- [ ] e2e: express succeeds from checkout; declined express surfaces there
- [ ] run full suite before Task 13

### Task 13: Server-authoritative amounts for existing renderers

Closes the live gap at `monei-google-apple-method.js:160` and `monei-paypal-method.js:113`.

**Files:**
- Modify: `monei-google-apple-method.js`, `monei-paypal-method.js`
- Modify: `Model/CheckoutConfigProvider.php` (wallet block, lines 268-288)
- Modify: `Test/Unit/Model/CheckoutConfigProviderTest.php`

- [ ] **do not wire up `getMethodConfig()`** while editing this file. It is private with zero
      callers, and line 462 sets `$paymentConfig['accountId'] = getApiKey()` — connecting it
      would publish the secret API key into `window.checkoutConfig`. Dormant today; leave it
      that way, or remove it separately
- [ ] serve amount and currency from `CheckoutConfigProvider`
- [ ] stop deriving the charged amount from `quote.totals()` in JS
- [ ] unit tests asserting the provider is the only amount source
- [ ] run full suite before Task 14

### Task 14: CSP whitelist for express origins

**Files:**
- Modify: `etc/frontend/csp_whitelist.xml`

- [ ] add PayPal and wallet origins to `frame-src`, `script-src`, `connect-src`
- [ ] verify manually in **both** report-only and enforced modes — failures here are silent
- [ ] unit test asserting the whitelist parses and contains the expected hosts
- [ ] run full suite before Task 15

### Task 15: Module-local PHPUnit configuration

There is no `phpunit.xml` here; `./bin test` borrows Magento's. CI cannot run without this.

**Files:**
- Create: `phpunit.xml.dist`
- Modify: `composer.json` (test script)

- [ ] decide: module-local `phpunit.xml.dist` with a framework stub (fast, no secrets) vs
      full-install matrix (slow, needs `repo.magento.com` auth keys). Default to module-local
- [ ] make all 418 methods pass under the new config
- [ ] verify `./bin test` still works for local use
- [ ] run full suite before Task 16

### Task 16: CI that runs the tests

**Files:**
- Create: `.github/workflows/test.yml`
- Modify: `.github/workflows/main.yml` (exclusions only, if needed)

- [ ] PHPUnit on PHP 8.1-8.4 using Task 15's module-local config — a module-local stub does
      not exercise 2.4.7 vs 2.4.8, so version compatibility is proven by the upgrade rehearsal
      in Post-Completion, not here. If a real version matrix is wanted instead, Task 15 must
      choose the full-install option and this job needs `repo.magento.com` secrets
- [ ] PHP lint / static analysis matching existing tooling
- [ ] Playwright job; MONEI test credentials as repository secrets, never in a file
- [ ] confirm the release zip still excludes `*Test*` and `tests/`
- [ ] CI green on the branch

### Task 17: Translations

Does not gate CI. Ship `en_US` plus the locales that can actually be reviewed.

**Files:**
- Modify: `i18n/en_US.csv` and the other 13 locale CSVs

- [ ] run `./bin i18n:collect-phrases` — do not hand-edit `en_US.csv`
- [ ] translate; flag any locale that could not be reviewed rather than guessing
- [ ] verify no untranslated string reaches the storefront in a reviewed locale

### Task 18: Verify acceptance criteria

- [ ] monei.js v3 live; card, Bizum, PayPal, wallet and the status poller all work
- [ ] split card fields default; single-field available by setting; `json_style` migrated
- [ ] express works from cart, mini-cart, product page and checkout
- [ ] the wallet sheet shows the shipping-inclusive total, updated live on option change
- [ ] the charged amount equals the total the sheet last displayed, and matches the order
- [ ] no client-supplied amount anywhere
- [ ] express failures surface on the originating surface with the cart intact
- [ ] express orders do not email before payment confirms
- [ ] full PHPUnit suite green (418 methods)
- [ ] full Playwright suite green **three consecutive runs**
- [ ] CI green

### Task 19: Update documentation

- [ ] `README.md`: new settings and express surfaces
- [ ] record the CardGroup default flip prominently — it changes existing checkouts
- [ ] document the wallet-sheet total limitation for merchants
- [ ] move this plan to `docs/plans/completed/`

## Risks

**Wallet sheet total — resolved, not a risk.** The handoff's "no shipping-change callback"
claim is false: `PaymentRequestProps` declares `onShippingAddressChange` and
`onShippingOptionChange`, both returning an updated amount. Task 7 supplies them, so the sheet
shows the shipping-inclusive total before approval. **Task 18 must still verify on real Apple
Pay and Google Pay hardware** — the prop contract is proven, the on-device behaviour is not.

**⚠️ The wallet sheet reports success before the charge is confirmed.** Apple Pay's
`session.completePayment()` fires on the tokenization result
(`monei-js/apps/payment-request/src/apple-pay.ts`), not the payment result. A decline lands
after the sheet has closed, on the merchant's page. This is why Task 11's failure surfacing is
a requirement rather than a nicety.

**⚠️ Carrier resolution now sits on the sheet's critical path.** `onShippingAddressChange`
must return options fast enough that the wallet does not time out, and must fail cleanly when
no carrier serves the address. A slow or throwing shipping-rate call degrades or hangs the
sheet. Task 7 owns this; measure it during Task 18.

**CardGroup as the default changes every merchant's checkout on upgrade**, and the shipped
`json_style` is CardInput-shaped. Task 3's style migration is the mitigation; the upgrade
rehearsal is the proof.

**Product-page express** is the most Magento-specific work and needs its own quote
endpoints (Task 10). Sequenced so Tasks 10 and the product bullet of Task 11 can be cut
together without stranding cart, mini-cart or checkout.

**Bizum cannot complete in test mode.** `E650` maps from Redsys `BIZ00202`. Not our defect.

## Post-Completion

**Manual verification:**
- Apple Pay on a real iOS device and Google Pay on a real Android device — specifically that
  `onShippingAddressChange` / `onShippingOptionChange` fire, the sheet total updates, and
  carrier resolution returns inside the wallet's timeout
- 3D Secure challenge through the tunnel, including the return leg
- Upgrade rehearsal from v2.2.4 with an existing merchant configuration and a customised
  `json_style`
- Express order cancel, refund and invoice-email behaviour, exercising all five plugins

**External:**
- Release via `release-tools:new`
- Trello card on MONEI Core Engineering — check for an existing card first; WooCommerce is 5570
- Consider publishing `monei-magento-dev-env`, mirroring `monei-prestashop-dev-env`
