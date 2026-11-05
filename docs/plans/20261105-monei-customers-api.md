# MONEI Customers API integration

## Overview
- Link the MONEI payments of logged-in buyers to a MONEI customer (`customerId`).
- Delete at MONEI when a buyer deletes a saved card, or when the Magento customer is deleted (this is also the GDPR erasure path).
- Magento Vault stays the source of truth for saved cards.
- Behavior is set by the cross-platform contract `docs/plans/20261105-monei-customers-api-contract.md` (v6). That file is a copy of the WooCommerce thread's file. Do not edit it. Contract changes come from the WooCommerce thread as a new version.
- Out of scope: list saved methods from MONEI, `useDefaultPaymentMethod`, attaching tokens saved before this change, MONEI Subscriptions, updating the MONEI customer on profile edits. Magento has no zero-amount add-card payment and no renewals, so sandbox check e does not apply.
- Tasks 9–11 are blocked until contract v7 (sandbox results). Do not run `/planning:exec` on this plan before v7: it would start the blocked tasks. Until then, implement Tasks 1–8 interactively.

## Context (from discovery)
- SDK `monei/monei-php-sdk` 2.13.0 is in `composer.lock` and `vendor/`. `$client->customers` has `create`, `delete`, `deletePaymentMethod`, `listPaymentMethods`. `CreatePaymentRequest` has `customer_id`. No dependency change.
- SDK facts (contract "SDK rules"):
  - Model setters throw on `null`.
  - `new MoneiClient($key)` with no config writes the key into the shared static `Configuration::getDefaultConfiguration()` (`vendor/monei/monei-php-sdk/lib/MoneiClient.php:73-74`).
- Store-scoped settings: `Model/Config/MoneiPaymentModuleConfig` — `getMode`, `getAccountId`, `getTestAccountId`, `getProductionAccountId`, `getTestApiKey`, `getProductionApiKey` (all take `$storeId`).
- SDK client: `Service/Api/MoneiApiClient::getMoneiSdk($storeId)` caches one client per store. `reinitialize()` replaces that cache.
- Payment creation: `Service/Api/CreatePayment::execute(array $data)`. It calls `executeMoneiSdkCall()` with no store ID, so the payment uses the **current store** (`AbstractApiService.php:140`, `MoneiApiClient.php:105`).
- Error path: `executeMoneiSdkCall()` sends an `ApiException` to `ApiExceptionHandler::handle()`, which logs at error level and throws a `LocalizedException` with no status code. So `CustomerService` does not use `executeMoneiSdkCall()`. It catches `\Monei\ApiException` itself and reads `getCode()`.
- Payment entry points:
  - `Service/Checkout/CreateLoggedMoneiPaymentInSite` (logged in),
  - `Service/Checkout/CreateLoggedMoneiPaymentVault` (logged in, saved card),
  - `Controller/Payment/Redirect` (guest or logged in, from the order),
  - `Service/Express/ExpressCheckout` (guest or logged in; it writes the wallet email onto the quote at line 278),
  - `Service/Checkout/CreateGuestMoneiPaymentInSite` (guest; no change).
- Vault: `PaymentTokenRepository::delete()` is a soft delete. `vault_payment_token.customer_id` has FK `ON DELETE CASCADE`.
- Customer delete: `CustomerRepository::deleteById()` → `Customer::delete()`. `customer_delete_commit_after` fires after commit. A throw there goes back up to the admin, so the observer must catch everything.
- Codes: `Monei::CARD_CODE = 'monei_card'`. Logging: `Service/Logger`.
- Commands: `composer test:unit`, `composer analyze` (PHPStan), `composer cs:lint`.

## Development Approach
- **testing approach**: Regular (code first, then tests).
- Complete each task fully before the next. Small, focused changes.
- Tests follow the user's rule 8: each test names the bug it catches. The contract sets tests 1–12. Other tests exist only for a branch that no contract test covers. Each one is listed with its bug.
- All tests pass before the next task. Update this plan when scope changes.

## Testing Strategy
- Unit tests with a mocked SDK client (`MoneiClient` with a mocked `customers` / `payments` property). Tests that need a real `Configuration` (test 9) build real `MoneiClient` objects and make no HTTP call.
- No e2e change: the shopper sees no difference.

## Progress Tracking
- Mark items `[x]` when done. New tasks get ➕. Blockers get ⚠️.

## Solution Overview
- One `CustomerService` (contract "Service") owns the mapping and every Customers API call. It is fail-open: a customer error is logged with the prefix `[MONEI Customers]` and never blocks checkout, a card delete or a customer delete.
- Entry points for logged-in buyers call `getOrCreateCustomerId()` with the **current store ID**, which is the store whose key `CreatePayment` uses. They pass the result as `customer_id` to `CreatePayment`, and only when the result is not `null`.
- The mapping lives in a new table, keyed by (Magento customer, account ID, mode).
- Cleanup triggers: a plugin on the Vault token repository (card delete, blocked on v7) and an observer on `customer_delete_commit_after` (customer delete / GDPR).

## Technical Details

### Table `monei_customer`
| Column | Type | Notes |
|---|---|---|
| `entity_id` | int unsigned, identity | PK |
| `customer_id` | int unsigned | Magento customer ID. No FK: the delete observer reads rows after the customer row is gone. |
| `account_id` | varchar(255) | MONEI account ID of the scope |
| `mode` | varchar(4) | `test` or `live` |
| `monei_customer_id` | varchar(255) | |
| `created_at` | timestamp | default CURRENT_TIMESTAMP |

Unique: (`customer_id`, `account_id`, `mode`).

### `CustomerServiceInterface` (contract signatures plus `storeId`, contract "scope parameter")
- `getOrCreateCustomerId(int $customerId, int $storeId, string $email, string $name, string $phone): ?string`
- `forgetCustomerId(int $customerId, int $storeId, string $staleCustomerId): void` — removes the row only while it still holds `$staleCustomerId` (`DELETE … WHERE monei_customer_id = :stale`).
- `deleteCustomer(int $customerId): void` — every row, all accounts and modes.
- `deletePaymentMethod(int $customerId, int $storeId, string $paymentToken): void` — added in Task 9.

Scope resolution: mode = `getMode($storeId)` → `test`|`live`; account = `getAccountId($storeId)`. An empty account ID means no link and one debug log. A `$customerId < 1` means no link: Express turns a guest's `null` customer into `0`.

### Create payload
`CreateCustomerRequest`:
- `email`: the customer account email. In Express, read `$quote->getCustomer()->getEmail()`, not the quote email, which is the wallet email.
- `name`: `trim(firstname . ' ' . lastname)`.
- `phone`: the billing address telephone of the quote or order. There is no customer phone field in Magento.
- `metadata`: `{"platform":"magento","platformCustomerId":"<id>"}`.
- An empty value (after `trim`) is left out, and its setter is never called (SDK rules). No other fields.

### SDK clients
- `getOrCreateCustomerId`, `forgetCustomerId`, `deletePaymentMethod`: use `MoneiApiClient::getMoneiSdk($storeId)`, the main client.
- `deleteCustomer`: for each row, use `MoneiApiClient::createSdkForApiKey($apiKey)` = `new MoneiClient($apiKey, new \Monei\Configuration())` plus the module User-Agent. No cache: one call per row.

### `deleteCustomer` key matching
Walk `StoreManager::getStores(true)`. For each store, collect (`getTestAccountId`, `test`, `getTestApiKey`) and (`getProductionAccountId`, `live`, `getProductionApiKey`), and skip pairs with an empty account ID or key. Take the mode from the row: the row matches the first pair with that mode whose account ID equals the row's account ID. Never pick a mode by account ID alone: Default/Website/Store scopes can hold the same account ID in test and live.

### Errors (contract table)
- Create fails: error log, return `null`, no retry.
- Concurrent create (unique-key conflict on insert: another request saved the mapping first): read the stored row, delete the MONEI customer just created (best effort; **error** log with its ID on failure), return the stored ID. The winning row is kept.
- Mapping save fails (nothing stored: another insert error, or the re-read after a conflict is empty): delete the MONEI customer just created (best effort; **error** log with its ID on failure), return `null`.
- Delete 404: debug log; `deleteCustomer` removes the row.
- `deleteCustomer` 409 or any other error: **error** log, keep the row, go on with the next row (each row in its own `\Throwable` catch).
- `deletePaymentMethod` 409: warning log; other error: error log. It never removes the mapping, not even on 404.
- Every case that can leave a MONEI customer with PII and no mapping logs at **error**: the module logs only errors by default.
- Every delete error log line holds the MONEI customer ID, the account ID and the mode. `monei_customer` has no FK, so a kept row survives the customer delete; the log line is the record for manual cleanup.
- Every customer code path catches `\Throwable` (not only `ApiException`).
- `deleteCustomer` row with no matching key: **error** log, keep the row, go on with the next row.
- Stale customer on create-payment: `forgetCustomerId(…, staleId)`, `offsetSet('customer_id', null)` (never `offsetUnset`: the serializer calls every getter and warns on a missing key), retry once (⚠️ v7, check c). Any other create-payment error is rethrown unchanged.

### Known limits (not fixed here)
- The existing `MoneiApiClient::getMoneiSdk()` builds every store client on the shared default `Configuration`. Two stores with different keys in one request share the last key. This is a bug that exists today and is out of scope.
- The SDK Guzzle client has no timeout. A MONEI outage that hangs the connection makes the admin customer delete wait.

## What Goes Where
- Implementation Steps: code and tests in this repo.
- Post-Completion: sandbox checks belong to the WooCommerce thread; manual store checks.

## Implementation Steps

### Task 1: Add the `monei_customer` mapping table and resource model

**Files:**
- Modify: `etc/db_schema.xml`
- Modify: `etc/db_schema_whitelist.json`
- Create: `Model/ResourceModel/MoneiCustomer.php`

- [x] add table `monei_customer` with the columns and unique key from Technical Details to `etc/db_schema.xml`
- [x] add the matching entries to `etc/db_schema_whitelist.json`
- [x] create `Model/ResourceModel/MoneiCustomer.php` (extends `AbstractDb`) with `getMoneiCustomerId(customerId, accountId, mode): ?string`, `saveRow(...)` (lets the duplicate-key exception go up), `deleteRow(customerId, accountId, mode)`, `getRowsByCustomer(customerId): array`
- [x] no unit test: the SQL is not unit-tested (a mock does not run it). It is checked in the manual store test (Post-Completion).
- [x] run `composer test:unit` and `composer analyze` - must pass before Task 2

### Task 2: Add `MoneiApiClient::createSdkForApiKey()`

**Files:**
- Modify: `Service/Api/MoneiApiClient.php`
- Modify: `Test/Unit/Service/Api/MoneiApiClientTest.php`

- [x] add `createSdkForApiKey(string $apiKey): MoneiClient` = `new MoneiClient($apiKey, new \Monei\Configuration())`, then `setUserAgent($this->getUserAgent())`. No cache, no change to `getMoneiSdk()`.
- [x] contract test 9: build the main client with key A, then a key client with key B; the main client's `getConfig()->getApiKey('Authorization')` is still A
- [x] ➕ contract v6 test 9: get the main client, then the other-mode client, then the main client AGAIN (`getMoneiSdk()` second call); the main client still holds key A and the other client key B
- [x] run `composer test:unit` - must pass before Task 3

### Task 3: `CustomerService` — `getOrCreateCustomerId` and `forgetCustomerId`

**Files:**
- Create: `Api/Service/Customer/CustomerServiceInterface.php`
- Create: `Service/Customer/CustomerService.php`
- Modify: `etc/di.xml`
- Create: `Test/Unit/Service/Customer/CustomerServiceTest.php`

- [x] create the interface with `getOrCreateCustomerId` and `forgetCustomerId` only, and the DI preference in `etc/di.xml` (same pattern as the other `Api/Service` interfaces)
- [x] implement scope resolution: mode → `test`|`live`, account ID; an empty account ID or `$customerId < 1` → debug log and `null`
- [x] implement `getOrCreateCustomerId`: mapping hit → return it. On a miss → `getMoneiSdk($storeId)->customers->create()` with the payload from Technical Details (empty fields left out), save the row, return the ID. Catch `\Throwable` → error log with `[MONEI Customers]`, return `null`. On a unique-key conflict on save → read the stored row, best-effort delete of the new MONEI customer (warning log on failure), return the stored ID.
- [x] implement `forgetCustomerId`: delete the row for the scope's account + mode
- [x] contract test 2: an existing mapping is reused, with no create call
- [x] contract test 3 (service part): a create error returns `null` and does not throw
- [x] contract test 4: a store in live mode never returns the test-mode ID of the same customer
- [x] contract test 8: an empty phone gives a create request with no `phone` key, and the ID is returned
- [x] extra test: `$customerId = 0` makes no create call. Bug: all Express guests are mapped to one MONEI customer with `platformCustomerId "0"`.
- [x] extra test: an empty account ID makes no create call. Bug: payments are rejected for a `customerId` that belongs to no configured account.
- [x] contract test 10: a concurrent create (unique-key conflict on save) returns the stored ID and deletes the new MONEI customer
- [x] ➕ v6: `forgetCustomerId(int $customerId, int $storeId, string $staleCustomerId)` removes the row only while it holds the stale ID; change `MoneiCustomer::deleteRow` (or add a method) to `DELETE … WHERE customer_id, account_id, mode AND monei_customer_id = :stale`; update the interface
- [x] ➕ v6: mapping save fails (any insert error other than the unique key, or an empty re-read after a conflict) → best-effort delete of the new MONEI customer, return `null`; a failed cleanup delete (both cases) logs at **error** with the new MONEI customer ID
- [x] ➕ v6 contract test 3: the create throws an `\Error` (not an `\Exception`); the result is `null` and no row is saved
- [x] ➕ v6 contract test 10: the concurrent create also keeps the winning row (no delete of the stored row); a mapping save that stores nothing returns `null` and deletes the new MONEI customer
- [x] run `composer test:unit` - must pass before Task 4

### Task 4: `CustomerService::deleteCustomer`

**Files:**
- Modify: `Api/Service/Customer/CustomerServiceInterface.php`
- Modify: `Service/Customer/CustomerService.php`
- Modify: `Test/Unit/Service/Customer/CustomerServiceTest.php`

- [x] add `deleteCustomer` to the interface
- [x] implement the key matching from Technical Details (all stores, test and production pairs), with a client from `createSdkForApiKey()` per row
- [x] for each row: no key → warning, keep; success or `ApiException` 404 → remove the row; 409 → warning, keep; any other `\Throwable` → error, keep. Every delete log line holds the MONEI customer ID, account ID and mode.
- [x] ➕ `forgetCustomerId` catches `\Throwable` and logs it with `[MONEI Customers]` (contract v5: every customer code path)
- [x] contract test 5: delete 404 is logged at debug level and the row is removed; a 500 keeps the row and logs at error level with the MONEI customer ID, account ID and mode
- [x] contract test 7: test and live use the SAME account ID; a test row and a live row give 2 deletes, each with the key of the row's own mode, and remove both rows; a row whose account matches no key is kept
- [x] ➕ v6: each row gets its own `\Throwable` catch, so a failing row (API, `\Error` or database) never stops the next rows; drop the outer catch that stops the loop
- [x] ➕ v6: `deleteCustomer` 409, other errors and a row with no matching key log at **error** (not warning), with the MONEI customer ID, account ID and mode
- [x] ➕ v6 contract test 7: when the first row throws an `\Error`, the next row is still deleted
- [x] run `composer test:unit` - must pass before Task 5

### Task 5: Send `customerId` from `CreatePayment`

**Files:**
- Modify: `Service/Api/CreatePayment.php`
- Modify: `Test/Unit/Service/Api/CreatePaymentTest.php`

- [x] in `buildPaymentRequest()`, call `setCustomerId()` only when `customer_id` is a non-empty string. Keep the inline `customer` block.
- [x] extra test: data with `customer_id` gives `CreatePaymentRequest::getCustomerId()` equal to it. Bug: the key is never read, so no payment is linked and every other test still passes.
- [x] run `composer test:unit` - must pass before Task 6

### Task 6: Link logged-in buyers at the entry points (not saved-card payments)

**Files:**
- Modify: `Controller/Payment/Redirect.php`
- Modify: `Service/Checkout/CreateLoggedMoneiPaymentInSite.php`
- Modify: `Service/Express/ExpressCheckout.php`
- Modify: `Test/Unit/Controller/Payment/RedirectTest.php`
- Modify: the constructor wiring of the matching existing tests, if they break

- [x] each entry point calls `getOrCreateCustomerId(customerId, current store ID, email, name, billing telephone)` and adds `customer_id` only when the result is not `null`
- [x] `Redirect`: only when `$order->getCustomerId()` is set; email and name from the order
- [x] `CreateLoggedMoneiPaymentInSite`: from the quote
- [x] `ExpressCheckout`: only when `$quote->getCustomerId()` is set; email from `$quote->getCustomer()->getEmail()`
- [x] contract test 1 in `RedirectTest`: an order with no customer ID makes no service call and sends no `customer_id`. Bug: orphan MONEI customers for guests.
- [x] contract test 3 (payload part) in `RedirectTest`: a logged-in order where the service returns `null` sends a payment with no `customer_id` key. Bug: a customer API outage blocks checkout.
- [x] wiring test in `RedirectTest`: a logged-in order sends the returned `customer_id`. Bug: the entry point never passes the ID.
- [x] run `composer test:unit` and `composer analyze` - must pass before Task 7

### Task 7: Delete the MONEI customer when the Magento customer is deleted

**Files:**
- Create: `Observer/DeleteMoneiCustomer.php`
- Modify: `etc/events.xml`
- Create: `Test/Unit/Observer/DeleteMoneiCustomerTest.php`

- [x] observer on `customer_delete_commit_after` calls `deleteCustomer((int) $customer->getId())`. It catches every throwable and logs it with `[MONEI Customers]`.
- [x] register it in `etc/events.xml` (global area, so admin, REST and storefront deletes run it)
- [x] one wiring test: the observer passes the deleted customer's ID, and an exception from the service does not escape. Bug: a MONEI failure blocks a customer delete in the admin.
- [x] run `composer test:unit` - must pass before Task 8

### Task 8: Verify Tasks 1–7
- [x] verify the unblocked contract items map to code: Service (3 methods), Mapping, SDK rules, Create request, `customerId` on checkout and Express payments, Errors (except the stale retry), concurrent create, customer-delete trigger, tests 1–5, 7–10 and 12
- [x] verify a guest checkout, the vault checkout and the express checkout still build their payloads (existing tests)
- [x] run the full suite: `composer test:unit`
- [x] run `composer analyze` and `composer cs:lint`
- [x] ➕ the failed delete of an unmapped MONEI customer (concurrent create / mapping save fails) logs the account ID and mode too; contract test 10 asserts them
- [x] ➕ `deleteCustomer` resolves the configured keys inside the per-row catch, so a config failure logs each row's MONEI customer ID, account ID and mode

### Task 9: Delete the MONEI payment method when a saved card is deleted (⚠️ blocked on contract v7, check a)

**Files:**
- Create: `Plugin/DeleteMoneiPaymentMethod.php`
- Modify: `etc/di.xml`
- Modify: `Api/Service/Customer/CustomerServiceInterface.php`
- Modify: `Service/Customer/CustomerService.php`
- Modify: `Test/Unit/Service/Customer/CustomerServiceTest.php`
- Create: `Test/Unit/Plugin/DeleteMoneiPaymentMethodTest.php`

- [ ] ⚠️ do not start before contract v7 states whether `CustomerPaymentMethod.id` equals the vault `gateway_token`
- [ ] add `deletePaymentMethod` to the interface. Implement it for the current scope: no mapping → nothing; else `customers->deletePaymentMethod(moneiCustomerId, token)`. If check a says the IDs differ, look the ID up via `listPaymentMethods` first. 404 → debug, 409 → warning, other → error. It never removes the mapping, whatever the result (v6).
- [ ] plugin `afterDelete` on `Magento\Vault\Api\PaymentTokenRepositoryInterface`: only tokens with `payment_method_code` = `Monei::CARD_CODE` and a customer ID; current store ID; never throws
- [ ] contract test 11: `deletePaymentMethod` with no mapping makes no API call
- [ ] one test: a non-MONEI token makes no service call. Bug: deleting a card of another payment provider calls MONEI.
- [ ] add the v7 test for check a, if v7 sets one
- [ ] run `composer test:unit` - must pass before Task 10

### Task 10: Retry once without a stale `customerId` (⚠️ blocked on contract v7, check c)

**Files:**
- Modify: `Service/Api/CreatePayment.php`
- Modify: the Task 6 entry points (pass `platform_customer_id`)
- Modify: `Test/Unit/Service/Api/CreatePaymentTest.php`

- [ ] ⚠️ do not start before contract v7 gives the exact error (status, code, message) for a deleted `customerId`
- [ ] entry points also pass `platform_customer_id` (the Magento ID). `buildPaymentRequest()` does not map it, so it is never sent.
- [ ] inside the `$sdkCall` closure (where the raw `ApiException` is visible): on that error, when `customer_id` and `platform_customer_id` are set → `forgetCustomerId(platform_customer_id, current store, staleId)`, `offsetSet('customer_id', null)`, retry the create once. Never a second retry. A failure of the retry is rethrown. Any other error is rethrown unchanged. If v7 says MONEI rejects a deleted customer only at confirm, not at create, this task is reopened per v7.
- [ ] contract test 6: a stale customer gives exactly one retry, the second request has no `customerId`, and the mapping is cleared; an unrelated error with the SAME HTTP status is rethrown and not retried; a payload without `customerId` is never retried
- [ ] run `composer test:unit` - must pass before Task 11

### Task 11: Link saved-card payments (⚠️ blocked on contract v7, check d)

**Files:**
- Modify: `Service/Checkout/CreateLoggedMoneiPaymentVault.php`
- Modify: `Test/Unit/Service/Checkout/CreateLoggedMoneiPaymentVaultTest.php` (only if v7 adds a test)

- [ ] ⚠️ do not start before contract v7 states whether MONEI accepts `customerId` with a token saved before the link
- [ ] if accepted: pass `customer_id` (and `platform_customer_id`) as in Tasks 6 and 10
- [ ] if rejected: follow the v7 rule (no `customerId` with an unlinked token) and add the v7 test
- [ ] run `composer test:unit` - must pass before Task 12

### Task 12: Verify acceptance criteria
- [ ] verify every contract item maps to code, including tests 1–12 and the v7 additions
- [ ] run the full suite: `composer test:unit`
- [ ] run `composer analyze` and `composer cs:lint`

### Task 13: [Final] Update documentation
- [ ] update `README.md` if it lists features: logged-in payments link to a MONEI customer; a customer delete deletes it at MONEI; a saved-card delete deletes it at MONEI (only with Task 9 done)

## Post-Completion
*Items requiring manual intervention or external systems - no checkboxes, informational only*

**Contract**
- Sandbox checks a and c–i are owned by the WooCommerce thread. Results arrive as contract v7. Copy v7 over `docs/plans/20261105-monei-customers-api-contract.md` unchanged, then do Tasks 9–11.
- v7 check f (does a customer delete remove its tokens) may add per-method deletes to `deleteCustomer` (Task 4). Check h (local vs E.164 phone) may change the phone rule (Task 3). Reopen those tasks with ➕ items if v7 says so. Check g does not apply: Magento has no zero-amount add-card payment.
- v7 check i (does every payment method accept `customerId` at create) may require Task 6 to leave `customer_id` out for some methods. `Redirect` serves every redirect method; `ExpressCheckout` serves Apple Pay / Google Pay. Reopen Task 6 with ➕ items if v7 says so.
- A Magento item that cannot follow the contract goes to the WooCommerce thread, not a local change.

**Manual verification**
- Run `bin/magento setup:upgrade` on a store to create the table.
- Test mode store: a logged-in card payment creates one MONEI customer (dashboard metadata `platform=magento`, `platformCustomerId`), and a second payment reuses it.
- Switch the store to live: the next payment creates a new live customer.
- Delete the customer in the admin: the MONEI customers in both modes are gone.
- Delete a saved card in My Account: the method is gone at MONEI (after Task 9).

**Follow-ups (not in this plan)**
- `getMoneiSdk()` shares the default SDK `Configuration` across stores (see Known limits).
- No HTTP timeout on the SDK client.
