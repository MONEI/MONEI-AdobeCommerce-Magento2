# MONEI Customers API: cross-platform contract (v4)

One behavior for the WooCommerce, Magento 2 and PrestaShop plugins. Each plugin
plan copies this file without changes. A change to the contract goes through
the WooCommerce thread, gets a new version number, and is sent to all three.

Docs: https://docs.monei.com/apis/rest/customers/
SDK: `monei/monei-php-sdk` 2.13.0, `$client->customers`.

## Changelog

- v4: concurrent create. When saving the mapping finds a stored ID, the new MONEI customer is deleted and the stored ID wins (no unmapped customer with PII). New test 10. Sandbox results move to v5.
- v3: SDK rules (empty values are left out, a field is removed and never set to `null`, each extra client gets its own `Configuration`); new sandbox check e (does a delete break a renewal sequence). Sandbox results move to v4.
- v2: a scope parameter is allowed; `deleteCustomer` deletes every mapping; phone fallback to the billing address; new sandbox check d; platform-specific flows apply only where the platform has them.
- v1: first version.

## Scope

In scope: link payments of logged-in buyers to a MONEI customer, and delete at
MONEI when a saved card or a buyer is deleted on the platform.

Out of scope: list saved methods from MONEI, `useDefaultPaymentMethod`,
attaching tokens saved before this change, MONEI Subscriptions, updating the
MONEI customer when the buyer edits their profile.

The platform token store stays the source of truth (WC tokens, Magento Vault,
`monei2_customer_card`).

## SDK rules (monei-php-sdk 2.13.0)

- The model setters throw `InvalidArgumentException` on `null` (`CreateCustomerRequest::setEmail/setName/setPhone/setMetadata`, `CreatePaymentRequest::setCustomerId`). Never pass `null`.
- To leave a field out: do not call its setter, or leave the array key out.
- To remove a field that is already set: `unset( $payload['customerId'] )` on an array payload, or `$request->offsetUnset('customer_id')` on a model.
- `new MoneiClient( $key )` with no config writes the key into the shared static `Configuration::getDefaultConfiguration()`, and every client that uses it then sends that key. A plugin that builds a client for a key other than its main one must use `new MoneiClient( $key, new \Monei\Configuration() )`. The main client does not change.

## Service

One service per plugin, named `CustomerService`. Method names follow the
platform's case convention (WooCommerce: snake_case).

A platform where the MONEI account, mode and API key depend on a scope adds
one scope parameter to the methods (Magento: `storeId`). "Current account +
mode" then means the account and mode configured for that scope. Behavior does
not change.

| Method | Behavior |
|---|---|
| `getOrCreateCustomerId(platformCustomerId, email, name, phone): ?string` | Returns the mapped ID for the current account + mode. With no mapping: create, store, return. Any error: log, return `null`. |
| `forgetCustomerId(platformCustomerId): void` | Removes the mapping for the current account + mode. |
| `deletePaymentMethod(platformCustomerId, paymentToken): void` | Current account + mode only. No mapping: do nothing. Calls `deletePaymentMethod(customerId, paymentToken)`. |
| `deleteCustomer(platformCustomerId): void` | **Every** mapping of the customer, for all accounts and modes. See below. |

`deleteCustomer`, for each mapping row of the customer:

1. Find the configured API key whose account ID and mode match the row. With no match: warning log, keep the row, go to the next row.
2. Call `delete(moneiCustomerId)` with a client for that key, built with its own `Configuration` (see SDK rules).
3. Success or 404: remove the row. 409 or other error: as in the Errors table.

It needs no current scope, so admin, REST and cron deletes work.

Guests have no `platformCustomerId`. The caller does not call the service for a guest.

## Mapping

Key: (`platformCustomerId`, MONEI account ID, mode `test`|`live`) → MONEI customer ID.

- WooCommerce: user meta `_monei_customer_{accountId}_{mode}`.
- Magento: new table `monei_customer` (`customer_id`, `account_id`, `mode`, `monei_customer_id`), unique on the first three.
- PrestaShop: new table `monei2_customer`, same columns (`id_customer`, `account_id`, `mode`, `monei_customer_id`), unique on the first three.

If the MONEI account ID setting for the current scope is empty, the plugin does
not link: no create call, no `customerId`. It writes one debug log line.

Saving a new mapping must detect a mapping that another request stored first
(two first payments of the same buyer at the same time):

- Magento, PrestaShop: insert; a unique-key conflict means another request won. Read the stored row.
- WooCommerce: `add_user_meta( …, $unique = true )`, then read the key again with `get_user_meta( …, true )`. If the value read is not the new ID, another request won. Remove a duplicate row with the new ID if one was written (`delete_user_meta( $user_id, $key, $newId )`).

When another request won, follow the "Concurrent create" row in the Errors table.

## Create request

`CreateCustomerRequest`:

- `email`, `name`: from the platform customer.
- `phone`: from the platform customer. If the customer has no phone field or it is empty, use the billing address of the current order or cart.
- Leave an empty value out of the request (see SDK rules). A buyer with no phone is still linked.
- `metadata`: `{"platform": "woocommerce"|"magento"|"prestashop", "platformCustomerId": "<id as string>"}`.

No other fields.

## When a payment carries `customerId`

On every create-payment call where the platform customer is known. Each item applies only where the platform has that flow:

- checkout payments (all MONEI methods, classic and block/one-page checkout),
- logged-in express checkout,
- the zero-amount "add card" payment,
- the first payment of a subscription.

Never on: guest payments, renewal and recurring charges (they already carry `sequenceId`).

The payload keeps its current inline `customer` block (email, name, phone).

Pending check d: a payment with a `paymentToken` saved before the link may need to go without `customerId`.

## Errors

All customer errors are logged with the prefix `[MONEI Customers]`. The shopper
never sees them, and they never stop a platform action.

| Case | Behavior |
|---|---|
| Create fails | Log error. The payment goes ahead without `customerId`. No retry. |
| Concurrent create (saving the mapping finds an ID stored by another request) | Delete the MONEI customer just created (best effort; warning log on failure). Return the stored ID. |
| Create-payment fails because the customer no longer exists (see check c) | `forgetCustomerId`, remove `customerId` from the request (see SDK rules), retry the create-payment once. Never a second retry. |
| Delete returns 404 | Debug log. For `deleteCustomer`, remove the row. |
| Delete returns 409 | Warning log. Keep the row. |
| Delete, other error | Error log. Keep the row. |
| `deleteCustomer` row with no matching configured key | Warning log. Keep the row. |

## Triggers

| Event | Call |
|---|---|
| Buyer deletes a saved MONEI card | `deletePaymentMethod` |
| Platform deletes the customer | `deleteCustomer` |
| GDPR erasure (Woo: `wp_privacy_personal_data_erasers`; PS: psgdpr `actionDeleteGDPRCustomer`; Magento: the customer delete) | `deleteCustomer` |

Pending check e: a platform with renewals may need to skip these deletes while an active platform subscription uses the token.

## Tests

Each plugin writes these unit tests, with a mocked SDK client. Each one names the bug it catches.

1. A guest payment sends no `customerId` and makes no create call. Bug: orphan customers for guests.
2. An existing mapping is reused with no create call. Bug: a new MONEI customer on every payment.
3. A create error returns `null` and the payment payload builds without `customerId`. Bug: customer API outage blocks checkout.
4. The mapping depends on mode: a test ID is never returned in live mode. Bug: live payments fail with a test customer ID.
5. Delete 404 is ignored and `deleteCustomer` still removes the row. Bug: old unlinked cards log errors, and erasure leaves a mapping.
6. A stale customer causes exactly one retry, the second request has no `customerId` key at all, and the mapping is cleared. Bug: a buyer deleted in the dashboard can never pay again, a retry loop, or a retry that throws on `setCustomerId(null)`.
7. `deleteCustomer` with a test row and a live row calls delete twice, each with the key of its own mode, and removes both rows. A row whose account matches no configured key is kept. Bug: an erasure leaves the other mode's customer and tokens at MONEI.
8. A buyer with no phone gets a create request with no `phone` key, and is linked. Bug: the SDK throws on a `null` phone, so those buyers are never linked.
9. Building the client for the other mode does not change the key of the main client. Bug: the shared default `Configuration` sends later calls with the wrong key.
10. A concurrent create returns the stored ID and deletes the new MONEI customer. Bug: an unmapped MONEI customer with PII that erasure never reaches.

## Open sandbox checks (owner: WooCommerce thread)

Results go into v5 of this contract.

- a. Is `CustomerPaymentMethod.id` the same string as `payment.paymentToken`? If not, `deletePaymentMethod` first finds the token through `listPaymentMethods`.
- b. When a payment sends `customerId` and an inline `customer` block, which details are kept on the payment?
- c. The exact error (HTTP status, code, message) for create-payment with a deleted `customerId`. Test 6 and the retry rule match on it.
- d. A payment sends `customerId` plus a `paymentToken` saved before the link (not under this customer). Does MONEI accept it? If it rejects it, the rule becomes: never send `customerId` with a token that is not linked, and v5 adds a test for it.
- e. Start a sequence (`sequence` on the first payment) under a customer, delete the payment method, then charge `recurring` on the `sequenceId`. Repeat with a customer delete. If the charge fails, v5 adds a rule: do not delete at MONEI a token or customer that an active platform subscription uses.
