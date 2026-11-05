# MONEI Customers API: cross-platform contract (v5)

One behavior for the WooCommerce, Magento 2 and PrestaShop plugins. Each plugin
plan copies this file without changes. A change to the contract goes through
the WooCommerce thread, gets a new version number, and is sent to all three.

Docs: https://docs.monei.com/apis/rest/customers/
SDK: `monei/monei-php-sdk` 2.13.0, `$client->customers`.

## Changelog

- v5:
  - A field is removed from a model with `offsetSet(…, null)`, not `offsetUnset`.
  - `deleteCustomer` matches each row on its own mode, never on the account ID alone, and needs a non-empty key.
  - Fail-open covers every `\Throwable`.
  - Delete error logs hold the MONEI customer ID, account ID and mode.
  - Tests 4, 5, 6 and 7 are tightened; new tests 11 and 12.
  - Sandbox check b is dropped; new checks f, g and h; checks c and the method for all checks are made precise.
  - Sandbox results move to v6.
- v4: concurrent create. When saving the mapping finds a stored ID, the new MONEI customer is deleted and the stored ID wins (no unmapped customer with PII). New test 10.
- v3: SDK rules (empty values are left out, a field is removed and never set to `null`, each extra client gets its own `Configuration`); new sandbox check e (does a delete break a renewal sequence).
- v2: a scope parameter is allowed; `deleteCustomer` deletes every mapping; phone fallback to the billing address; new sandbox check d; platform-specific flows apply only where the platform has them.
- v1: first version.

## Scope

In scope: link payments of logged-in buyers to a MONEI customer, and delete at
MONEI when a saved card or a buyer is deleted on the platform.

Out of scope: list saved methods from MONEI, `useDefaultPaymentMethod`,
attaching tokens saved before this change, MONEI Subscriptions, updating the
MONEI customer when the buyer edits their profile, a retry queue for failed
deletes.

The platform token store stays the source of truth (WC tokens, Magento Vault,
`monei2_customer_card`).

## SDK rules (monei-php-sdk 2.13.0)

- The model setters throw `InvalidArgumentException` on `null` (`CreateCustomerRequest::setEmail/setName/setPhone/setMetadata`, `CreatePaymentRequest::setCustomerId`). Never pass `null` to a setter.
- To leave a field out: do not call its setter, or leave the array key out.
- To remove a field that is already set:
  - array payload: `unset( $payload['customerId'] )`;
  - model: `$request->offsetSet( 'customer_id', null )`. Do not use `offsetUnset`: the getter then reads a missing array key, and the serializer calls every getter, which gives a PHP warning that can corrupt an AJAX response. The serializer leaves out `null` values of non-nullable fields, and `offsetExists` returns false.
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
| `getOrCreateCustomerId(platformCustomerId, email, name, phone): ?string` | Returns the mapped ID for the current account + mode. With no mapping: create, store, return. Any `\Throwable`: log, return `null`. |
| `forgetCustomerId(platformCustomerId): void` | Removes the mapping for the current account + mode. |
| `deletePaymentMethod(platformCustomerId, paymentToken): void` | Current account + mode only. No mapping: no API call. Calls `deletePaymentMethod(customerId, paymentToken)`. |
| `deleteCustomer(platformCustomerId): void` | **Every** mapping of the customer, for all accounts and modes. See below. |

`deleteCustomer`, for each mapping row of the customer:

1. Take the mode from the row. The row matches when the account ID configured for that mode equals the row's account ID and the API key of that mode is not empty. Never find a mode by account ID alone: test and live often share one account ID. With no match: warning log, keep the row, go to the next row.
2. Call `delete(moneiCustomerId)` with a client for that mode's key, built with its own `Configuration` (see SDK rules).
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

No other fields. Check h decides whether a phone that is not E.164 can be sent as it is.

## When a payment carries `customerId`

On every create-payment call where the platform customer is known. Each item applies only where the platform has that flow:

- checkout payments (all MONEI methods, classic and block/one-page checkout),
- logged-in express checkout,
- the zero-amount "add card" payment (pending check g),
- the first payment of a subscription.

Never on: guest payments, renewal and recurring charges (they already carry `sequenceId`).

The payload keeps its current inline `customer` block (email, name, phone).

Pending check d: a payment with a `paymentToken` saved before the link may need to go without `customerId`.

## Errors

All customer errors are logged with the prefix `[MONEI Customers]`. The shopper
never sees them, and they never stop a platform action: every customer code
path catches `\Throwable`, not only API errors.

Every delete error log line holds the MONEI customer ID, the account ID and the
mode, at the level in the table. A platform delete can remove the mapping row
right after the hook (WooCommerce `wp_delete_user` deletes all user meta), so
the log line can be the only record left to clean up by hand.

| Case | Behavior |
|---|---|
| Create fails | Log error. The payment goes ahead without `customerId`. No retry. |
| Concurrent create (saving the mapping finds an ID stored by another request) | Delete the MONEI customer just created (best effort; warning log on failure). Return the stored ID. |
| Create-payment fails because the customer no longer exists (see check c) | `forgetCustomerId`, remove `customerId` from the request (see SDK rules), retry the create-payment once. Never a second retry. Any other create-payment error is rethrown unchanged. |
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
3. A create error (a generic `\Exception`, not only an API error) gives a payload without `customerId`, and no mapping is stored. Bug: customer API outage blocks checkout, or an empty ID is stored and reused.
4. Test and live use the **same** account ID. A test-mode mapping is never returned in live mode. Bug: a key without the mode lets live payments send a test customer ID.
5. Delete 404 is logged at debug level and `deleteCustomer` removes the row. Delete with another error (for example 500) keeps the row and logs at error level with the MONEI customer ID, account ID and mode. Bug: old unlinked cards log errors; an outage drops the mapping and leaves the customer at MONEI.
6. A stale customer causes exactly one retry, the second request has no `customerId`, and the mapping is cleared. An unrelated error with the **same** HTTP status is rethrown and not retried, and a payload without `customerId` is never retried. Bug: a buyer deleted in the dashboard can never pay again, a retry loop, a retry that throws on `setCustomerId(null)`, or a swallowed payment error.
7. Test and live use the **same** account ID. `deleteCustomer` with a test row and a live row calls delete twice, each with the key of the row's own mode, and removes both rows. A row whose account matches no configured key is kept. Bug: a live row deleted with the test key gets 404, the row is removed, and the live customer stays at MONEI.
8. A buyer with no phone gets a create request with no `phone` key, and is linked. Bug: the SDK throws on a `null` phone, so those buyers are never linked.
9. Build the main client, then the client for the other mode. The main client still sends its own key, and the other client sends the other mode's key. Bug: the shared default `Configuration` sends later calls with the wrong key.
10. A concurrent create returns the stored ID and deletes the new MONEI customer. Bug: an unmapped MONEI customer with PII that erasure never reaches.
11. `deletePaymentMethod` with no mapping makes no API call. Bug: every card delete of an unlinked buyer calls MONEI with an empty customer ID and logs an error.
12. An empty account ID gives no create call, no `customerId` and no stored mapping. Bug: a mapping stored under an empty account that no delete can resolve.

## Open sandbox checks (owner: WooCommerce thread)

Results go into v6 of this contract.

Method: each card payment is completed in a browser at its `nextAction.redirectUrl` with a MONEI test card (a server-side create alone stays pending, so it has no token and no sequence).

- a. Is `CustomerPaymentMethod.id` the same string as `payment.paymentToken`? If not, `deletePaymentMethod` first finds the token through `listPaymentMethods`.
- b. Dropped in v5: no decision depends on it. The inline `customer` block stays.
- c. The exact error (HTTP status, code, message) for create-payment with a deleted `customerId`. Test 6 and the retry rule match on it. Also record whether the retry succeeds when the first request carried a one-time monei.js `paymentToken`. If it fails, the retry helps only the flows without a one-time token, which is acceptable.
- d. A payment sends `customerId` plus a `paymentToken` saved before the link (not under this customer). Does MONEI accept it? If it rejects it, the rule becomes: never send `customerId` with a token that is not linked, and v6 adds a test for it.
- e. Start a sequence (`sequence` on the first payment) under a customer, delete the payment method, then charge `recurring` on the `sequenceId`. Repeat with a customer delete. If the charge fails, v6 adds a rule: do not delete at MONEI a token or customer that an active platform subscription uses.
- f. After a customer delete, does a payment with one of its saved `paymentToken`s fail? The docs say a customer delete deletes its payment methods. If the token still works, `deleteCustomer` must also delete each method first.
- g. A zero-amount `transactionType: VERIFY` payment with `customerId` and `generatePaymentToken`: is it accepted, and is the card saved under the customer?
- h. `customers->create` with a local phone (`600 123 456`) and with `+34600123456`. If the local format is rejected, v6 adds a rule: send the phone only when it is E.164 (`^\+[1-9]\d{6,14}$`), else leave it out.
